<?php

namespace App\Filament\Resources\PenyewaanResource\Pages;

use App\Filament\Resources\PenyewaanResource;

use Filament\Resources\Pages\CreateRecord;

// models
use App\Models\PenyewaanMotor;
use App\Models\Motor;
use App\Models\Pelanggan; 

// notification
use Filament\Notifications\Notification;

class CreatePenyewaan extends CreateRecord
{
    protected static string $resource = PenyewaanResource::class;

    /**
     * Hitung total sebelum disimpan ke DB
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $total = 0;

        if (isset($data['penyewaanMotor'])) {
            foreach ($data['penyewaanMotor'] as $item) {
                $total += (float) ($item['subtotal'] ?? 0);
            }
        }

        $data['total_harga'] = $total;

        return $data;
    }

    /**
     * Setelah data berhasil disimpan
     */
    protected function afterCreate(): void
    {
        // ambil data penyewaan yang baru disimpan
        $penyewaan = $this->record;

        // ambil detail motor
        $detailMotor = PenyewaanMotor::where(
            'penyewaan_id',
            $penyewaan->id_sewa
        )->get();

        // update status motor menjadi disewa
        foreach ($detailMotor as $item) {

            $motor = Motor::find($item->motor_id);

            if ($motor) {

                $motor->update([
                    'status' => 'disewa'
                ]);
            }
        }
        
         // ── Kirim notifikasi WA ──────────────────────────────
        $pelanggan = Pelanggan::find($penyewaan->pelanggan_id);

        if ($pelanggan) {
            $nomor = preg_replace('/[^0-9]/', '', $pelanggan->no_telepon ?? '');

            if ($nomor) {
                if (str_starts_with($nomor, '0')) {
                    $nomor = '62' . substr($nomor, 1);
                }

                $nama      = $pelanggan->nama_pelanggan ?? 'Pelanggan';
                $no_faktur = $penyewaan->no_faktur ?? '-';
                $tgl_sewa  = $penyewaan->tgl_sewa ?? '-';
                $tgl_kembali = $penyewaan->tgl_kembali ?? '-';
                $metode    = strtoupper($penyewaan->metode ?? '-');
                $total     = number_format((float) $penyewaan->total_harga, 0, ',', '.');

                $pesan  = "Halo {$nama},\n\n";
                $pesan .= "Pembayaran sewa motor Anda di Bintang Rental Motor telah berhasil diproses.\n\n";
                $pesan .= "No Faktur: {$no_faktur}\n";
                $pesan .= "Tanggal Sewa: {$tgl_sewa}\n";
                $pesan .= "Tanggal Kembali: {$tgl_kembali}\n";
                $pesan .= "Metode Pembayaran: {$metode}\n";
                $pesan .= "Total Bayar: Rp{$total}\n\n";
                $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor.";

                $fonnte = app(\App\Services\FonnteService::class);
                $fonnte->sendMessage($nomor, $pesan);
            }
        }

        // notifikasi sukses
        Notification::make()
            ->title('Penyewaan Berhasil Disimpan!')
            ->success()
            ->send();
    }
}