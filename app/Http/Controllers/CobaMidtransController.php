<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penyewaan;
use App\Models\Pembayaran;
use App\Models\PenyewaanMotor;

class CobaMidtransController extends Controller
{
    /**
     * Generate Snap Token untuk penyewaan tertentu (dipanggil dari CreatePenyewaan).
     */
    public function getSnapTokenBySewa(Penyewaan $penyewaan)
    {
        $penyewaan->load(['pelanggan', 'penyewaanMotor.motor']);
        
        // ── Setup Midtrans ────────────────────────────────────────────────
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        // ── Cek apakah snap token sudah ada & belum expired di Midtrans ──
        $pembayaran = Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();

        if ($pembayaran && $pembayaran->transaction_id) {
            // Cek status order di Midtrans
            $orderId = $pembayaran->order_id;
            $url     = 'https://api.sandbox.midtrans.com/v2/' . $orderId . '/status';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, config('midtrans.server_key') . ':');
            $output     = curl_exec($ch);
            curl_close($ch);
            $outputJson = json_decode($output, true);

            // Jika belum expired → pakai snap token yang sudah ada
            if (
                ! in_array($outputJson['transaction_status'] ?? '', ['expire', 'cancel', 'deny'])
                && ($outputJson['status_code'] ?? '') !== '404'
            ) {
                return [
                    'success'    => true,
                    'snap_token' => $pembayaran->transaction_id,
                ];
            }

            // Expired/cancel → reset pembayaran agar bisa generate baru
            $pembayaran->update([
                'status_code'      => null,
                'transaction_time' => null,
                'total_harga'      => 0,
                'transaction_id'   => null,
            ]);
        }

        // ── Buat order_id baru format: no_faktur-YmdHis ──────────────────
        $orderId = $penyewaan->no_faktur . '-' . date('YmdHis');

        // ── Susun item details dari penyewaan_motor ───────────────────────
        $itemDetails = [];
        foreach ($penyewaan->penyewaanMotor as $d) {
            $itemDetails[] = [
                'id'       => (string) $d->id,
                'price'    => (int) $d->subtotal,
                'quantity' => 1,
                'name'     => ($d->motor->nama_motor ?? 'Motor')
                              . ' '
                              . number_format((float) $d->jml, 0) . ' hari',
            ];
        }

        // ── Customer details dari relasi pelanggan ────────────────────────
        $pelanggan = $penyewaan->pelanggan;
        $customerDetails = [
            'first_name' => $pelanggan?->nama_pelanggan ?? 'Pelanggan',
            'email'      => $pelanggan?->email          ?? 'pelanggan@sewa.id',
            'phone'      => $pelanggan?->no_telepon     ?? '08000000000',
        ];

        // ── Params Midtrans ───────────────────────────────────────────────
        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $penyewaan->total_harga,
            ],
            'item_details'     => $itemDetails,
            'customer_details' => $customerDetails,
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit'       => 'minutes',
                'duration'   => 60,   // expired dalam 60 menit
            ],
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // Simpan / update ke tabel pembayaran
            Pembayaran::updateOrCreate(
                ['penyewaan_id' => $penyewaan->id_sewa],
                [
                    'tgl_bayar'        => now()->toDateString(),
                    'metode' => 'midtrans',
                    'transaction_time' => now(),
                    'total_harga'      => $penyewaan->total_harga,
                    'order_id'         => $orderId,
                    'payment_type'     => 'pg',
                    'status_code'      => '201',   // pending
                    'status_message'   => 'Pending payment',
                    'transaction_id'   => $snapToken,  // simpan snap token di sini
                ]
            );

            return [
                'success'    => true,
                'snap_token' => $snapToken,
            ];

        } catch (\Exception $e) {
            throw $e; // re-throw untuk ditangkap di CreatePenyewaan
        }
    }

    /**
     * Generate Snap Token untuk penyewaan tertentu.
     * Dipanggil via fetch() dari Step 3 wizard Filament (PenyewaanResource).
     * POST /midtrans-sewa/snap-token
     */
    public function getSnapToken(Request $request)
    {
        $noFaktur = $request->input('no_faktur');

        if (! $noFaktur) {
            return response()->json(['success' => false, 'message' => 'No faktur tidak ditemukan.'], 422);
        }

        $penyewaan = Penyewaan::with(['pelanggan', 'penyewaanMotor.motor'])->where('no_faktur', $noFaktur)->first();

        if (! $penyewaan) {
            return response()->json(['success' => false, 'message' => 'Data penyewaan tidak ditemukan.'], 404);
        }

        // ── Setup Midtrans ────────────────────────────────────────────────
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        // ── Cek apakah snap token sudah ada & belum expired di Midtrans ──
        $pembayaran = Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();

        if ($pembayaran && $pembayaran->transaction_id) {
            // Cek status order di Midtrans
            $orderId = $pembayaran->order_id;
            $url     = 'https://api.sandbox.midtrans.com/v2/' . $orderId . '/status';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, config('midtrans.server_key') . ':');
            $output     = curl_exec($ch);
            curl_close($ch);
            $outputJson = json_decode($output, true);

            // Jika belum expired → pakai snap token yang sudah ada
            if (
                ! in_array($outputJson['transaction_status'] ?? '', ['expire', 'cancel', 'deny'])
                && ($outputJson['status_code'] ?? '') !== '404'
            ) {
                return response()->json([
                    'success'    => true,
                    'snap_token' => $pembayaran->transaction_id,
                ]);
            }

            // Expired/cancel → reset pembayaran agar bisa generate baru
            $pembayaran->update([
                'status_code'      => null,
                'transaction_time' => null,
                'total_harga'      => 0,
                'transaction_id'   => null,
            ]);
        }

        // ── Buat order_id baru format: no_faktur-YmdHis ──────────────────
        $orderId = $noFaktur . '-' . date('YmdHis');

        // ── Susun item details dari penyewaan_motor ───────────────────────
        $itemDetails = [];
        foreach ($penyewaan->penyewaanMotor as $d) {
            $itemDetails[] = [
                'id'       => (string) $d->id,
                'price'    => (int) $d->subtotal,
                'quantity' => 1,
                'name'     => ($d->nama_motor ?? 'Motor')
                              . ' '
                              . number_format((float) $d->jml, 0) . ' hari',
            ];
        }

        // ── Customer details dari relasi pelanggan ────────────────────────
        $pelanggan = $penyewaan->pelanggan;
        $customerDetails = [
            'first_name' => $pelanggan?->nama_pelanggan ?? 'Pelanggan',
            'email'      => $pelanggan?->email          ?? 'pelanggan@sewa.id',
            'phone'      => $pelanggan?->no_telepon     ?? '08000000000',
        ];

        // ── Params Midtrans ───────────────────────────────────────────────
        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $penyewaan->total_harga,
            ],
            'item_details'     => $itemDetails,
            'customer_details' => $customerDetails,
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit'       => 'minutes',
                'duration'   => 60,   // expired dalam 60 menit
            ],
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // Simpan / update ke tabel pembayaran
            Pembayaran::updateOrCreate(
                ['penyewaan_id' => $penyewaan->id_sewa],
                [
                    'tgl_bayar'        => now()->toDateString(),
                    'metode' => 'midtrans',
                    'transaction_time' => now(),
                    'total_harga'      => $penyewaan->total_harga,
                    'order_id'         => $orderId,
                    'payment_type'     => 'pg',
                    'status_code'      => '201',   // pending
                    'status_message'   => 'Pending payment',
                    'transaction_id'   => $snapToken,  // simpan snap token di sini
                ]
            );

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
     * Midtrans akan POST ke /midtrans-sewa/callback setiap ada perubahan status.
     * POST /midtrans-sewa/callback  (di routes/api.php, bebas CSRF)
     */
    public function handleCallback(Request $request)
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        $serverKey = config('midtrans.server_key');

        // 1. Validasi signature key
        $hashed = hash('sha512',
            $request->order_id
            . $request->status_code
            . $request->gross_amount
            . $serverKey
        );

        if ($hashed !== $request->signature_key) {
            \Log::warning('Midtrans Callback Sewa: Signature Key tidak valid untuk Order ID: ' . $request->order_id);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // 2. Ambil data dari request
        $orderId         = $request->order_id;
        $status          = $request->transaction_status;
        $statusCode      = $request->status_code;
        $transactionTime = $request->transaction_time;
        $settlementTime  = $request->settlement_time ?? null;
        $statusMessage   = $request->status_message  ?? null;
        $merchantId      = $request->merchant_id     ?? null;

        // 3. Potong order_id untuk dapatkan no_faktur asli
        // format: SEW-0000001-20260511123456 → SEW-0000001
        $parts    = explode('-', $orderId);
        $noFaktur = $parts[0] . '-' . $parts[1]; // SEW-0000001

        // 4. Update tabel pembayaran
        Pembayaran::where('order_id', $orderId)->update([
            'status_code'      => $statusCode    ?? null,
            'transaction_time' => $transactionTime ?? null,
            'settlement_time'  => $settlementTime  ?? null,
            'status_message'   => $statusMessage   ?? null,
            'merchant_id'      => $merchantId      ?? null,
        ]);

        // 5. Jika sudah settlement (terbayar) → update status_bayar penyewaan
        if ($statusCode === '200') {
            Penyewaan::where('no_faktur', $noFaktur)
                ->update(['status_bayar' => 'lunas']);
        }

        // 6. Jika expired/cancel/deny → reset pembayaran ke awal
        if (in_array($status, ['expire', 'cancel', 'deny'])) {
            Pembayaran::where('order_id', $orderId)->update([
                'status_code'      => null,
                'transaction_time' => null,
                'total_harga'      => 0,
                'transaction_id'   => null,
            ]);
        }

        return response()->json(['message' => 'OK']);
    }

    /**
     * Cek status pembayaran pending secara manual (auto-refresh).
     * Dipanggil dari admin panel jika perlu sync manual.
     * GET /midtrans-sewa/cek-status
     */
    public function cekStatus()
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        // Ambil semua pembayaran via PG yang belum terbayar
        $pending = Pembayaran::whereIn('payment_type', ['pg', 'midtrans'])
            ->whereRaw("IFNULL(status_code, '0') <> '200'")
            ->orderBy('tgl_bayar', 'desc')
            ->get();

        $updated = 0;
        $expired = 0;

        foreach ($pending as $p) {
            if (! $p->order_id) {
                continue;
            }

            $url = 'https://api.sandbox.midtrans.com/v2/' . $p->order_id . '/status';
            $ch  = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, config('midtrans.server_key') . ':');
            $output     = curl_exec($ch);
            curl_close($ch);
            $out = json_decode($output, true);

            if (in_array($out['transaction_status'] ?? '', ['expire', 'cancel', 'deny'])) {
                // Reset
                $p->update([
                    'status_code'      => null,
                    'transaction_time' => null,
                    'total_harga'      => 0,
                    'transaction_id'   => null,
                ]);
                $expired++;
            } elseif (($out['status_code'] ?? '') === '200') {
                // Update terbayar
                $p->update([
                    'status_code'     => '200',
                    'settlement_time' => $out['settlement_time'] ?? null,
                    'status_message'  => $out['status_message']  ?? null,
                    'merchant_id'     => $out['merchant_id']     ?? null,
                ]);

                // Update status_bayar penyewaan
                $parts    = explode('-', $p->order_id);
                $noFaktur = $parts[0] . '-' . $parts[1];
                Penyewaan::where('no_faktur', $noFaktur)->update(['status_bayar' => 'lunas']);
                $updated++;
            }
        }

        return response()->json([
            'message' => "Selesai. Terbayar: {$updated}, Expired/Cancel: {$expired}.",
        ]);
    }
}