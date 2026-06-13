<?php

namespace App\Http\Controllers; 

use Illuminate\Http\Request; 
use App\Models\Penyewaan; 
use App\Models\Pembayaran; 
use App\Models\PenyewaanMotor;

class CobaMidtransController extends Controller
{
    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Setup konfigurasi Midtrans.
     */
    private function setupMidtrans(): void
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;
    }

    /**
     * Cek status order di Midtrans API.
     * Return array hasil JSON dari Midtrans, atau null jika gagal.
     */
    private function checkMidtransStatus(string $orderId): ?array
    {
        $url = 'https://api.sandbox.midtrans.com/v2/' . $orderId . '/status';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,           $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH,       CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD,        config('midtrans.server_key') . ':');
        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output, true) ?? null;
    }

    /**
     * Reset record pembayaran ke state awal (token expired/cancel/deny).
     */
    private function resetPembayaran(Pembayaran $pembayaran): void
    {
        $pembayaran->update([
            'status_code'      => null,
            'transaction_time' => null,
            'total_harga'      => 0,
            'transaction_id'   => null,
        ]);
    }

    /**
     * Potong order_id menjadi no_faktur.
     * Format order_id: S-0000001-20260511123456 → S-0000001
     */
    private function extractNoFaktur(string $orderId): string
    {
        $parts = explode('-', $orderId);
        return ($parts[0] ?? '') . '-' . ($parts[1] ?? '');
    }

    /**
     * Cek & kembalikan snap token yang masih valid, atau null jika harus generate baru.
     * Return array ['action' => 'reuse'|'reset'|'generate', 'snap_token' => ?string]
     */
    private function resolveExistingToken(Pembayaran $pembayaran): array
    {
        if (! $pembayaran->transaction_id) {
            return ['action' => 'generate'];
        }

        $status            = $this->checkMidtransStatus($pembayaran->order_id);
        $transactionStatus = $status['transaction_status'] ?? '';
        $statusCode        = $status['status_code']        ?? '';

        // Sudah terbayar → jangan buka popup lagi
        if (in_array($transactionStatus, ['settlement', 'capture'])) {
            // Pastikan status_bayar di DB ikut terupdate
            Penyewaan::where('no_faktur', $this->extractNoFaktur($pembayaran->order_id))
                ->update(['status_bayar' => 'lunas']);

            return ['action' => 'paid'];
        }

        // Expired / cancel / deny → generate token baru
        if (in_array($transactionStatus, ['expire', 'cancel', 'deny']) || $statusCode === '404') {
            $this->resetPembayaran($pembayaran);
            return ['action' => 'reset'];
        }

        // Masih pending & belum expired → reuse token
        return [
            'action'     => 'reuse',
            'snap_token' => $pembayaran->transaction_id,
        ];
    }

    /**
     * Simpan / update record pembayaran setelah generate snap token.
     */
    private function savePembayaran(Penyewaan $penyewaan, string $orderId, string $snapToken): void
    {
        Pembayaran::updateOrCreate(
            ['penyewaan_id' => $penyewaan->id_sewa],
            [
                'tgl_bayar'        => now()->toDateString(),
                'metode'           => 'midtrans',
                'transaction_time' => now(),
                'total_harga'      => $penyewaan->total_harga,
                'order_id'         => $orderId,
                'payment_type'     => 'pg',
                'status_code'      => '201',
                'status_message'   => 'Pending payment',
                'transaction_id'   => $snapToken,
            ]
        );
    }

    /**
     * Susun params untuk Midtrans Snap::getSnapToken().
     */
    private function buildSnapParams(Penyewaan $penyewaan, string $orderId): array
    {
        $itemDetails = $penyewaan->penyewaanMotor->map(fn ($d) => [
            'id'       => (string) $d->id,
            'price'    => (int) $d->subtotal,
            'quantity' => 1,
            'name'     => ($d->motor->nama_motor ?? 'Motor') . ' ' . (int) $d->jml . ' hari',
        ])->toArray();

        $pelanggan = $penyewaan->pelanggan;

        return [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $penyewaan->total_harga,
            ],
            'item_details'     => $itemDetails,
            'customer_details' => [
                'first_name' => $pelanggan?->nama_pelanggan ?? 'Pelanggan',
                'email'      => $pelanggan?->email          ?? 'pelanggan@sewa.id',
                'phone'      => $pelanggan?->no_telepon     ?? '08000000000',
            ],
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit'       => 'minutes',
                'duration'   => 60,
            ],
        ];
    }

    // =========================================================================
    // PUBLIC METHODS
    // =========================================================================

    /**
     * Generate Snap Token untuk penyewaan tertentu.
     * Dipanggil via route binding (bukan dari wizard Filament).
     */
    public function getSnapTokenBySewa(Penyewaan $penyewaan): array
    {
        $penyewaan->load(['pelanggan', 'penyewaanMotor.motor']);
        $this->setupMidtrans();

        $pembayaran = Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();

        if ($pembayaran) {
            $resolved = $this->resolveExistingToken($pembayaran);

            if ($resolved['action'] === 'paid') {
                return ['success' => false, 'message' => 'Transaksi ini sudah terbayar.'];
            }

            if ($resolved['action'] === 'reuse') {
                return ['success' => true, 'snap_token' => $resolved['snap_token']];
            }
        }

        // Generate snap token baru
        $orderId = $penyewaan->no_faktur . '-' . date('YmdHis');
        $params  = $this->buildSnapParams($penyewaan, $orderId);

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
            $this->savePembayaran($penyewaan, $orderId, $snapToken);

            return ['success' => true, 'snap_token' => $snapToken];

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Generate Snap Token via fetch() dari Step 3 wizard Filament.
     * POST /midtrans/snap-token
     */
    public function getSnapToken(Request $request)
    {
        $noFaktur = $request->input('no_faktur');

        if (! $noFaktur) {
            return response()->json(['success' => false, 'message' => 'No faktur tidak ditemukan.'], 422);
        }

        $penyewaan = Penyewaan::with(['pelanggan', 'penyewaanMotor.motor'])
            ->where('no_faktur', $noFaktur)
            ->first();

        if (! $penyewaan) {
            return response()->json(['success' => false, 'message' => 'Data penyewaan tidak ditemukan.'], 404);
        }

        $this->setupMidtrans();

        $pembayaran = Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();

        if ($pembayaran) {
            $resolved = $this->resolveExistingToken($pembayaran);

            if ($resolved['action'] === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi ini sudah terbayar.',
                    'paid'    => true,
                ], 422);
            }

            if ($resolved['action'] === 'reuse') {
                return response()->json([
                    'success'    => true,
                    'snap_token' => $resolved['snap_token'],
                ]);
            }
        }

        // Generate snap token baru
        $orderId = $noFaktur . '-' . date('YmdHis');
        $params  = $this->buildSnapParams($penyewaan, $orderId);

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
            $this->savePembayaran($penyewaan, $orderId, $snapToken);

            return response()->json([
                'success'    => true,
                'snap_token' => $snapToken,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate Snap Token: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook callback dari Midtrans (Notification URL).
     * POST /api/midtrans/callback  (di routes/api.php, bebas CSRF)
     */
    public function handleCallback(Request $request)
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        // 1. Validasi signature key
        $hashed = hash('sha512',
            $request->order_id
            . $request->status_code
            . $request->gross_amount
            . config('midtrans.server_key')
        );

        if ($hashed !== $request->signature_key) {
            \Log::warning('Midtrans Callback: Signature tidak valid. Order ID: ' . $request->order_id);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $orderId           = $request->order_id;
        $transactionStatus = $request->transaction_status;
        $statusCode        = $request->status_code;
        $noFaktur          = $this->extractNoFaktur($orderId);

        // 2. Update tabel pembayaran
        Pembayaran::where('order_id', $orderId)->update([
            'status_code'      => $statusCode                  ?? null,
            'transaction_time' => $request->transaction_time   ?? null,
            'settlement_time'  => $request->settlement_time    ?? null,
            'status_message'   => $request->status_message     ?? null,
            'merchant_id'      => $request->merchant_id        ?? null,
        ]);

        // 3. Settlement → tandai lunas
        if ($statusCode === '200') {
            Penyewaan::where('no_faktur', $noFaktur)
                ->update(['status_bayar' => 'lunas']);
        }

        // 4. Expired / cancel / deny → reset pembayaran
        if (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
            $pembayaran = Pembayaran::where('order_id', $orderId)->first();
            if ($pembayaran) {
                $this->resetPembayaran($pembayaran);
            }
        }

        return response()->json(['message' => 'OK']);
    }

    /**
     * Sync status pembayaran pending secara manual.
     * GET /midtrans/cek-status
     */
    public function cekStatus()
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        $pending = Pembayaran::whereIn('payment_type', ['pg', 'midtrans'])
            ->whereRaw("IFNULL(status_code, '0') <> '200'")
            ->orderBy('tgl_bayar', 'desc')
            ->get();

        $updated = 0;
        $expired = 0;

        foreach ($pending as $pembayaran) {
            if (! $pembayaran->order_id) continue;

            $out               = $this->checkMidtransStatus($pembayaran->order_id);
            $transactionStatus = $out['transaction_status'] ?? '';
            $statusCode        = $out['status_code']        ?? '';

            if (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
                $this->resetPembayaran($pembayaran);
                $expired++;

            } elseif ($statusCode === '200') {
                $pembayaran->update([
                    'status_code'     => '200',
                    'settlement_time' => $out['settlement_time'] ?? null,
                    'status_message'  => $out['status_message']  ?? null,
                    'merchant_id'     => $out['merchant_id']     ?? null,
                ]);

                Penyewaan::where('no_faktur', $this->extractNoFaktur($pembayaran->order_id))
                    ->update(['status_bayar' => 'lunas']);

                $updated++;
            }
        }

        return response()->json([
            'message' => "Selesai. Terbayar: {$updated}, Expired/Cancel: {$expired}.",
        ]);
    }
}