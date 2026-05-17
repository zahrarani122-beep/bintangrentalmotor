<?php

namespace App\Http\Controllers;

use App\Models\Pengembalian;
use App\Models\Penyewaan;
use App\Services\FonnteService;

class NotificationController extends Controller
{
    protected $fonnteService;

    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
    }

    /**
     * Notifikasi umum untuk testing.
     */
    public function kirimNotifikasi()
    {
        $nomor_tujuan = '081321405677';

        $pesan = "Halo, pelanggan Bintang Rental Motor.\n\n";
        $pesan .= "Ini adalah pesan notifikasi dari sistem Bintang Rental Motor.\n";
        $pesan .= "Terima kasih telah menggunakan layanan kami.";

        return $this->sendAndRespond($nomor_tujuan, $pesan, 'Pesan terkirim!');
    }

    /**
     * Notifikasi ketika penyewaan berhasil dibuat.
     */
    public function kirimNotifikasiPenyewaan($id_penyewaan)
    {
        $penyewaan = Penyewaan::with(['pelanggan', 'penyewaanMotor.motor'])
            ->find($id_penyewaan);

        if (! $penyewaan) {
            return response()->json([
                'message' => 'Data penyewaan tidak ditemukan.'
            ], 404);
        }

        $pelanggan = $penyewaan->pelanggan;

        if (! $pelanggan) {
            return response()->json([
                'message' => 'Data pelanggan tidak ditemukan.'
            ], 404);
        }

        $nomor_tujuan = $this->ambilNomorPelanggan($pelanggan);

        if (! $nomor_tujuan) {
            return response()->json([
                'message' => 'Nomor HP pelanggan belum tersedia.'
            ], 400);
        }

        $nama = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $no_faktur = $penyewaan->no_faktur ?? '-';
        $tgl_sewa = $penyewaan->tgl_sewa ?? '-';
        $tgl_kembali = $penyewaan->tgl_kembali ?? '-';
        $metode = strtoupper($penyewaan->metode ?? '-');
        $total = $penyewaan->total_harga ?? 0;

        $daftarMotor = '';
        foreach ($penyewaan->penyewaanMotor as $item) {
            $namaMotor = $item->motor->nama_motor ?? 'Motor';
            $durasi = $item->jml ?? 0;
            $subtotal = $item->subtotal ?? 0;

            $daftarMotor .= "- {$namaMotor} ({$durasi} hari) - Rp"
                . number_format((float) $subtotal, 0, ',', '.')
                . "\n";
        }

        $pesan = "Halo {$nama}, 👋\n\n";
        $pesan .= "Penyewaan motor Anda berhasil dibuat. ✅\n\n";
        $pesan .= "📄 No Faktur: {$no_faktur}\n";
        $pesan .= "📅 Tanggal Sewa: {$tgl_sewa}\n";
        $pesan .= "📅 Estimasi Kembali: {$tgl_kembali}\n";
        $pesan .= "💳 Metode Pembayaran: {$metode}\n\n";
        $pesan .= "🏍️ Motor yang Disewa:\n{$daftarMotor}\n";
        $pesan .= "💰 Total Bayar: Rp"
            . number_format((float) $total, 0, ',', '.')
            . "\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor. 🙏";

        return $this->sendAndRespond(
            $nomor_tujuan,
            $pesan,
            'Notifikasi penyewaan berhasil dikirim!'
        );
    }

    /**
     * Notifikasi ketika pembayaran berhasil/lunas.
     */
    public function kirimNotifikasiPembayaran($id_penyewaan)
    {
        $penyewaan = Penyewaan::with('pelanggan')->find($id_penyewaan);

        if (! $penyewaan) {
            return response()->json([
                'message' => 'Data penyewaan tidak ditemukan.'
            ], 404);
        }

        $pelanggan = $penyewaan->pelanggan;

        if (! $pelanggan) {
            return response()->json([
                'message' => 'Data pelanggan tidak ditemukan.'
            ], 404);
        }

        $nomor_tujuan = $this->ambilNomorPelanggan($pelanggan);

        if (! $nomor_tujuan) {
            return response()->json([
                'message' => 'Nomor HP pelanggan belum tersedia.'
            ], 400);
        }

        $nama = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $no_faktur = $penyewaan->no_faktur ?? '-';
        $total = $penyewaan->total_harga ?? 0;

        $pesan = "Halo {$nama}, 👋\n\n";
        $pesan .= "Pembayaran penyewaan Anda telah kami terima. ✅\n\n";
        $pesan .= "📄 No Faktur: {$no_faktur}\n";
        $pesan .= "💰 Total Bayar: Rp"
            . number_format((float) $total, 0, ',', '.')
            . "\n";
        $pesan .= "📅 Tanggal Bayar: " . now()->format('d/m/Y H:i') . "\n\n";
        $pesan .= "Terima kasih telah melakukan pembayaran. 🙏";

        return $this->sendAndRespond(
            $nomor_tujuan,
            $pesan,
            'Notifikasi pembayaran berhasil dikirim!'
        );
    }

    /**
     * Notifikasi pengembalian motor.
     */
    public function kirimNotifikasiPengembalian($id_pengembalian)
    {
        $pengembalian = Pengembalian::with('penyewaan.pelanggan')
            ->where('id_pengembalian', $id_pengembalian)
            ->first();

        if (! $pengembalian) {
            return response()->json([
                'message' => 'Data pengembalian tidak ditemukan.'
            ], 404);
        }

        $penyewaan = $pengembalian->penyewaan;
        $pelanggan = $penyewaan?->pelanggan;

        if (! $pelanggan) {
            return response()->json([
                'message' => 'Data pelanggan tidak ditemukan.'
            ], 404);
        }

        $nomor_tujuan = $this->ambilNomorPelanggan($pelanggan);

        if (! $nomor_tujuan) {
            return response()->json([
                'message' => 'Nomor HP pelanggan belum tersedia.'
            ], 400);
        }

        $nama_pelanggan = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $no_faktur = $penyewaan->no_faktur ?? '-';
        $tgl_pengembalian = $pengembalian->tgl_pengembalian ?? '-';
        $denda = $pengembalian->denda ?? 'Tidak Ada Denda';
        $total = $pengembalian->total ?? 0;
        $keterangan = $pengembalian->keterangan ?? '-';

        $pesan = "Halo {$nama_pelanggan},\n\n";
        $pesan .= "Pengembalian motor Anda di Bintang Rental Motor telah berhasil diproses.\n\n";
        $pesan .= "No Faktur: {$no_faktur}\n";
        $pesan .= "Tanggal Pengembalian: {$tgl_pengembalian}\n";
        $pesan .= "Status Denda: {$denda}\n";
        $pesan .= "Total Denda: Rp" . number_format((float) $total, 0, ',', '.') . "\n";
        $pesan .= "Keterangan: {$keterangan}\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor.";

        return $this->sendAndRespond(
            $nomor_tujuan,
            $pesan,
            'Notifikasi pengembalian berhasil dikirim!'
        );
    }

    /**
     * Kirim pesan custom.
     */
    public function sendMessage($target, $message)
    {
        return $this->sendAndRespond($target, $message, 'Pesan terkirim!');
    }

    /**
     * Ambil nomor pelanggan dari beberapa kemungkinan nama field.
     */
    private function ambilNomorPelanggan($pelanggan)
    {
        $nomor = $pelanggan->no_hp
            ?? $pelanggan->no_telp
            ?? $pelanggan->no_telepon
            ?? $pelanggan->nomor_hp
            ?? $pelanggan->telepon
            ?? null;

        if (! $nomor) {
            return null;
        }

        return $this->formatNomorWa($nomor);
    }

    /**
     * Helper untuk mengirim dan mengembalikan response JSON.
     */
    private function sendAndRespond($target, $message, $successMessage)
    {
        $target = $this->formatNomorWa($target);

        $proses = $this->fonnteService->sendMessage($target, $message);

        if (($proses['status'] ?? false) == true) {
            return response()->json([
                'message' => $successMessage
            ]);
        }

        return response()->json([
            'message' => 'Gagal: ' . ($proses['reason'] ?? 'Tidak diketahui')
        ], 500);
    }

    /**
     * Format nomor WA menjadi 62xxxxxxxxxx.
     */
    private function formatNomorWa($nomor)
    {
        $nomor = preg_replace('/[^0-9]/', '', $nomor);

        if (substr($nomor, 0, 1) === '0') {
            $nomor = '62' . substr($nomor, 1);
        }

        return $nomor;
    }
}
