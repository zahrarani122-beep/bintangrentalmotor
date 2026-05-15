<?php

namespace App\Filament\Resources\PengembalianResource\Pages;

use App\Filament\Resources\PengembalianResource;
use App\Services\FonnteService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class CreatePengembalian extends CreateRecord
{
    protected static string $resource = PengembalianResource::class;

    protected function afterCreate(): void
    {
        $pengembalian = $this->record;

        $pengembalian->load('penyewaan.pelanggan', 'penyewaan.penyewaanMotor.motor');

        /*
        |--------------------------------------------------------------------------
        | 1. Ubah status motor menjadi tersedia
        |--------------------------------------------------------------------------
        */
        if ($pengembalian->penyewaan && $pengembalian->penyewaan->penyewaanMotor) {
            foreach ($pengembalian->penyewaan->penyewaanMotor as $detail) {
                if ($detail->motor) {
                    $detail->motor->update(['status' => 'tersedia']);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Generate PDF Invoice
        |--------------------------------------------------------------------------
        */
        $penyewaan = $pengembalian->penyewaan;
        $pelanggan = $penyewaan?->pelanggan;

        $pdf      = Pdf::loadView('pdf.invoice', [
            'pengembalian' => $pengembalian,
            'penyewaan'    => $penyewaan,
        ]);
        $namaFile = 'invoice-' . ($penyewaan->no_faktur ?? $pengembalian->id_pengembalian) . '.pdf';
        $path     = storage_path('app/public/' . $namaFile);
        $pdf->save($path);
        //$urlPdf   = config('app.url') . '/storage/' . $namaFile;
        /*
        |--------------------------------------------------------------------------
        | 3. Kirim WhatsApp notifikasi + link PDF
        |--------------------------------------------------------------------------
        */
        if (!$pelanggan) {
            Notification::make()
                ->title('Notifikasi tidak dikirim')
                ->body('Data pelanggan tidak ditemukan.')
                ->warning()
                ->send();
            return;
        }

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

        $namaPelanggan   = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $noFaktur        = $penyewaan->no_faktur ?? '-';
        $tglPengembalian = $pengembalian->tgl_pengembalian ?? '-';
        $statusDenda     = $pengembalian->denda ?? 'Tidak Ada Denda';
        $totalSewa       = number_format((float) $penyewaan->total_harga, 0, ',', '.');
        $totalDenda      = number_format((float) ($pengembalian->total ?? 0), 0, ',', '.');
        $grandTotal      = number_format((float) $penyewaan->total_harga + (float) ($pengembalian->total ?? 0), 0, ',', '.');
        $keterangan      = $pengembalian->keterangan ?? '-';

        $pesan  = "Halo {$namaPelanggan},\n\n";
        $pesan .= "Pengembalian motor Anda di Bintang Rental Motor telah berhasil diproses.\n\n";
        $pesan .= "No Faktur: {$noFaktur}\n";
        $pesan .= "Tanggal Pengembalian: {$tglPengembalian}\n";
        $pesan .= "Status Denda: {$statusDenda}\n";
        $pesan .= "Total Sewa: Rp{$totalSewa}\n";
        $pesan .= "Total Denda: Rp{$totalDenda}\n";
        $pesan .= "Grand Total: Rp{$grandTotal}\n";
        $pesan .= "Keterangan: {$keterangan}\n\n";
        $pesan .= "Invoice PDF: {$urlPdf}\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor.";

        $fonnteService = app(FonnteService::class);
        $proses = $fonnteService->sendMessage($nomorTujuan, $pesan);
        $fonnteService->sendFile($nomorTujuan, $urlPdf, "Invoice Pengembalian - {$noFaktur}");

        if (($proses['status'] ?? false) == true) {
            Notification::make()
                ->title('Notifikasi WhatsApp terkirim')
                ->body('Pesan + invoice PDF berhasil dikirim ke pelanggan.')
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