<?php

namespace App\Filament\Resources\PengembalianResource\Pages;

use App\Filament\Resources\PengembalianResource;
use App\Services\FonnteService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePengembalian extends CreateRecord
{
    protected static string $resource = PengembalianResource::class;

    protected function afterCreate(): void
    {
        $pengembalian = $this->record;

        // Ambil data penyewaan, pelanggan, dan motor
        $pengembalian->load('penyewaan.pelanggan', 'penyewaan.penyewaanMotor.motor');

        /*
        |--------------------------------------------------------------------------
        | 1. Ubah status motor menjadi tersedia
        |--------------------------------------------------------------------------
        */
        if ($pengembalian->penyewaan && $pengembalian->penyewaan->penyewaanMotor) {
            foreach ($pengembalian->penyewaan->penyewaanMotor as $detail) {
                if ($detail->motor) {
                    $detail->motor->update([
                        'status' => 'tersedia',
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Kirim WhatsApp notifikasi pengembalian
        |--------------------------------------------------------------------------
        */
        $penyewaan = $pengembalian->penyewaan;
        $pelanggan = $penyewaan?->pelanggan;

        if (!$pelanggan) {
            Notification::make()
                ->title('Notifikasi tidak dikirim')
                ->body('Data pelanggan tidak ditemukan.')
                ->warning()
                ->send();

            return;
        }

        // Sesuaikan dengan nama kolom nomor HP di tabel pelanggan kamu
        $nomorTujuan = $pelanggan->no_hp
            ?? $pelanggan->no_telp
            ?? $pelanggan->no_telepon
            ?? $pelanggan->telepon
            ?? null;

        if (!$nomorTujuan) {
            Notification::make()
                ->title('Notifikasi tidak dikirim')
                ->body('Nomor HP pelanggan belum tersedia.')
                ->warning()
                ->send();

            return;
        }

        $namaPelanggan = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $noFaktur = $penyewaan->no_faktur ?? '-';
        $tglPengembalian = $pengembalian->tgl_pengembalian ?? '-';
        $statusDenda = $pengembalian->denda ?? 'Tidak Ada Denda';
        $totalDenda = $pengembalian->total ?? 0;
        $keterangan = $pengembalian->keterangan ?? '-';

        $pesan = "Halo {$namaPelanggan},\n\n";
        $pesan .= "Pengembalian motor Anda di Bintang Rental Motor telah berhasil diproses.\n\n";
        $pesan .= "No Faktur: {$noFaktur}\n";
        $pesan .= "Tanggal Pengembalian: {$tglPengembalian}\n";
        $pesan .= "Status Denda: {$statusDenda}\n";
        $pesan .= "Total Denda: Rp" . number_format((float) $totalDenda, 0, ',', '.') . "\n";
        $pesan .= "Keterangan: {$keterangan}\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor.";

        $fonnteService = app(FonnteService::class);
        $proses = $fonnteService->sendMessage($nomorTujuan, $pesan);

        if (($proses['status'] ?? false) == true) {
            Notification::make()
                ->title('Notifikasi WhatsApp terkirim')
                ->body('Pesan pengembalian berhasil dikirim ke pelanggan.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Notifikasi WhatsApp gagal')
                ->body($proses['reason'] ?? 'Gagal mengirim pesan ke Fonnte.')
                ->danger()
                ->send();
        }
    }
}