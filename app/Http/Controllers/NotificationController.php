<?php

namespace App\Http\Controllers;

use App\Models\Pengembalian;
use App\Services\FonnteService;

class NotificationController extends Controller
{
    protected $fonnteService;

    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
    }

    public function kirimNotifikasi()
    {
        $nomor_tujuan = '081321405677';

        $pesan = "Halo, pelanggan Bintang Rental Motor.\n\n";
        $pesan .= "Ini adalah pesan notifikasi dari sistem Bintang Rental Motor.\n";
        $pesan .= "Terima kasih telah menggunakan layanan kami.";

        $proses = $this->fonnteService->sendMessage($nomor_tujuan, $pesan);

        if (($proses['status'] ?? false) == true) {
            return response()->json([
                'message' => 'Pesan terkirim!'
            ]);
        }

        return response()->json([
            'message' => 'Gagal: ' . ($proses['reason'] ?? 'Tidak diketahui')
        ], 500);
    }

    // KATA KATA UNTUK PENGEMBALIAN
    public function kirimNotifikasiPengembalian($id_pengembalian)
    {
        $pengembalian = Pengembalian::with('penyewaan.pelanggan')
            ->where('id_pengembalian', $id_pengembalian)
            ->first();

        if (!$pengembalian) {
            return response()->json([
                'message' => 'Data pengembalian tidak ditemukan.'
            ], 404);
        }

        $penyewaan = $pengembalian->penyewaan;
        $pelanggan = $penyewaan?->pelanggan;

        if (!$pelanggan) {
            return response()->json([
                'message' => 'Data pelanggan tidak ditemukan.'
            ], 404);
        }

        $nomor_tujuan = $pelanggan->no_hp
            ?? $pelanggan->no_telp
            ?? $pelanggan->no_telepon
            ?? $pelanggan->nomor_hp
            ?? $pelanggan->telepon
            ?? null;

        if (!$nomor_tujuan) {
            return response()->json([
                'message' => 'Nomor HP pelanggan belum tersedia.'
            ], 400);
        }

        $nomor_tujuan = $this->formatNomorWa($nomor_tujuan);

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

        $proses = $this->fonnteService->sendMessage($nomor_tujuan, $pesan);

        if (($proses['status'] ?? false) == true) {
            return response()->json([
                'message' => 'Notifikasi pengembalian berhasil dikirim!'
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim notifikasi: ' . ($proses['reason'] ?? 'Tidak diketahui')
        ], 500);
    }

    public function sendMessage($target, $message)
    {
        $target = $this->formatNomorWa($target);

        $proses = $this->fonnteService->sendMessage($target, $message);

        if (($proses['status'] ?? false) == true) {
            return response()->json([
                'message' => 'Pesan terkirim!'
            ]);
        }

        return response()->json([
            'message' => 'Gagal: ' . ($proses['reason'] ?? 'Tidak diketahui')
        ], 500);
    }

    private function formatNomorWa($nomor)
    {
        $nomor = preg_replace('/[^0-9]/', '', $nomor);

        if (substr($nomor, 0, 1) === '0') {
            $nomor = '62' . substr($nomor, 1);
        }

        return $nomor;
    }
}