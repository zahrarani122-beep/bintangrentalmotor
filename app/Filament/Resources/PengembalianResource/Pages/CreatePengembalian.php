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

        $pengembalian->load([
            'penyewaan.pelanggan',
            'penyewaan.penyewaanMotor.motor',
        ]);

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
        | 2. Kirim notifikasi WhatsApp pengembalian
        |--------------------------------------------------------------------------
        */
        $penyewaan = $pengembalian->penyewaan;
        $pelanggan = $penyewaan?->pelanggan;

        if (!$pelanggan) {
            Notification::make()
                ->title('Notifikasi WhatsApp gagal')
                ->body('Data pelanggan tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        $nomorTujuan = $pelanggan->no_hp
            ?? $pelanggan->no_telp
            ?? $pelanggan->no_telepon
            ?? $pelanggan->nomor_hp
            ?? $pelanggan->telepon
            ?? null;

        if (!$nomorTujuan) {
            Notification::make()
                ->title('Notifikasi WhatsApp gagal')
                ->body('Nomor HP pelanggan belum tersedia.')
                ->danger()
                ->send();

            return;
        }

        $namaPelanggan = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $noFaktur = $penyewaan->no_faktur ?? '-';

        $namaMotor = $penyewaan->penyewaanMotor
            ->pluck('motor.nama_motor')
            ->filter()
            ->implode(', ');

        if (!$namaMotor) {
            $namaMotor = '-';
        }

        $tglPengembalian = $pengembalian->tgl_pengembalian
        ? \Carbon\Carbon::parse($pengembalian->tgl_pengembalian)->format('Y-m-d')
        : '-';

        $waktuPengembalian = now('Asia/Jakarta')->format('H:i:s') . ' WIB';

        $statusDenda = $pengembalian->denda ?? 'Tidak Ada Denda';
        $totalDenda = $pengembalian->total ?? 0;
        $keterangan = $pengembalian->keterangan ?? '-';

        $detailDenda = $pengembalian->detail_denda ?? [];

        if (is_string($detailDenda)) {
            $detailDenda = json_decode($detailDenda, true) ?? [];
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Susun isi pesan WhatsApp
        |--------------------------------------------------------------------------
        */
        $pesan = "Halo {$namaPelanggan},\n\n";
        $pesan .= "Pengembalian motor Anda di Bintang Rental Motor telah berhasil diproses.\n\n";

        $pesan .= "DETAIL PENGEMBALIAN\n";
        $pesan .= "No Faktur : {$noFaktur}\n";
        $pesan .= "Motor : {$namaMotor}\n";
        $pesan .= "Tanggal Pengembalian : {$tglPengembalian}\n";
        $pesan .= "Waktu Pengembalian : {$waktuPengembalian}\n\n";

        $pesan .= "DETAIL DENDA\n";
        $pesan .= "Status Denda : {$statusDenda}\n";

        if ($statusDenda === 'Ada Denda' && count($detailDenda) > 0) {
            foreach (array_values($detailDenda) as $index => $item) {
                $jenisDenda = $item['jenis_denda'] ?? '-';
                $namaDenda = $item['nama_denda'] ?? '-';
                $nominal = $item['nominal'] ?? 0;

                $pesan .= "Denda " . ($index + 1) . ":\n";
                $pesan .= "- Jenis Denda : {$jenisDenda}\n";
                $pesan .= "- Nama Denda : {$namaDenda}\n";
                $pesan .= "- Nominal : Rp" . number_format((float) $nominal, 0, ',', '.') . "\n";
            }
        } else {
            $pesan .= "Detail Denda : Tidak ada denda\n";
        }

        $pesan .= "\nTotal Denda : Rp" . number_format((float) $totalDenda, 0, ',', '.') . "\n";
        $pesan .= "Keterangan : {$keterangan}\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor.";

        /*
        |--------------------------------------------------------------------------
        | 4. Kirim pesan melalui Fonnte
        |--------------------------------------------------------------------------
        */
        $fonnteService = app(FonnteService::class);
        $proses = $fonnteService->sendMessage($nomorTujuan, $pesan);

        if (($proses['status'] ?? false) == true) {
            Notification::make()
                ->title('Notifikasi WhatsApp terkirim')
                ->body('Pesan pengembalian berhasil dikirim ke pelanggan.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Notifikasi WhatsApp gagal')
            ->body($proses['reason'] ?? 'Gagal mengirim pesan.')
            ->danger()
            ->send();
    }
}