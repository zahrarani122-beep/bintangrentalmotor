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
     * Override: kalau no_faktur sudah ada (sudah diproses via simpanPenyewaan),
     * update saja — jangan buat baru. Buang penyewaanMotor dari $data agar
     * Filament tidak insert ulang (sudah dihandle oleh simpanPenyewaan).
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Buang repeater — sudah di-insert manual oleh simpanPenyewaan()
        unset($data['penyewaanMotor']);

        // Buang field yang tidak ada di tabel penyewaan
        unset($data['metode'], $data['nominal_bayar'], $data['kembalian']);

        $existing = \App\Models\Penyewaan::where('no_faktur', $data['no_faktur'])->first();

        if ($existing) {
            // Jangan overwrite total_harga dan status_bayar —
            // keduanya sudah diset benar oleh simpanPenyewaan() & tombol Simpan Pembayaran
            $updateData = $data;
            unset($updateData['total_harga'], $updateData['status_bayar']);
            $existing->update($updateData);
            return $existing;
        }

        // Record belum ada (tidak melalui tombol Proses Penyewaan) → default belum_bayar
        $data['status_bayar'] = 'belum_bayar';
        return parent::handleRecordCreation($data);
    }

    /**
     * Hitung total sebelum disimpan ke DB.
     * Karena simpanPenyewaan() sudah insert penyewaanMotor,
     * kita hanya perlu strip field yang tidak relevan.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Buang field form-only yang tidak ada di tabel penyewaan
        unset($data['metode'], $data['nominal_bayar'], $data['kembalian']);

        // Jangan set status_bayar di sini — sudah dihandle oleh:
        // - simpanPenyewaan() → default belum_bayar
        // - tombol Simpan Pembayaran → lunas jika tunai
        // - webhook Midtrans handleCallback() → lunas jika settlement

        return $data;
    }

    /**
     * Setelah data berhasil disimpan
     */
    protected function afterCreate(): void
    {
        $penyewaan = $this->record;

        // Baca metode dari tabel pembayaran (bukan form state — field metode dehydrated=false)
        $pembayaran = \App\Models\Pembayaran::where('penyewaan_id', $penyewaan->id_sewa)->first();
        $metode     = $pembayaran?->metode ?? null;

        // Buka Midtrans popup jika metode midtrans
        if ($metode === 'midtrans') {
            try {
                $response  = app(\App\Http\Controllers\CobaMidtransController::class)
                    ->getSnapTokenBySewa($penyewaan);
                $snapToken = $response['snap_token'] ?? null;

                if ($snapToken) {
                    $this->dispatch('open-midtrans', token: $snapToken);
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Gagal membuka Midtrans')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        // Update status motor → disewa
        $detailMotor = PenyewaanMotor::where('penyewaan_id', $penyewaan->id_sewa)->get();
        foreach ($detailMotor as $item) {
            $motor = Motor::find($item->motor_id);
            if ($motor) {
                $motor->update(['status' => 'disewa']);
            }
        }

        // Kirim notifikasi WA (tunai maupun midtrans)
        if ($metode) {
            $this->sendWhatsAppNotification($penyewaan, $metode);
        }

        Notification::make()
            ->title('Penyewaan Berhasil Disimpan!')
            ->success()
            ->send();
    }

    /**
     * Kirim notifikasi WhatsApp
     */
    private function sendWhatsAppNotification($penyewaan, string $metode = '-'): void
    {
        $pelanggan = Pelanggan::find($penyewaan->pelanggan_id);

        if (! $pelanggan) return;

        $nomor = preg_replace('/[^0-9]/', '', $pelanggan->no_telepon ?? '');

        if (! $nomor) return;

        if (str_starts_with($nomor, '0')) {
            $nomor = '62' . substr($nomor, 1);
        }

        $nama        = $pelanggan->nama_pelanggan ?? 'Pelanggan';
        $no_faktur   = $penyewaan->no_faktur ?? '-';
        $tgl_sewa    = $penyewaan->tgl_sewa ?? '-';
        $tgl_kembali = $penyewaan->tgl_kembali ?? '-';
        // Metode dari parameter (bukan $penyewaan->metode — kolom tidak ada di tabel penyewaan)
        $metodeLabel = match ($metode) {
            'tunai'    => 'Tunai',
            'midtrans' => 'Midtrans (Online)',
            'transfer' => 'Transfer Bank',
            default    => strtoupper($metode),
        };
        $total = number_format((float) $penyewaan->total_harga, 0, ',', '.');

        $pesan  = "Halo {$nama},\n\n";
        $pesan .= "Pembayaran motor Anda di Bintang Rental Motor telah berhasil diproses.\n\n";
        $pesan .= "No Faktur: {$no_faktur}\n";
        $pesan .= "Tanggal Sewa: {$tgl_sewa}\n";
        $pesan .= "Tanggal Kembali: {$tgl_kembali}\n";
        $pesan .= "Metode Pembayaran: {$metodeLabel}\n";
        $pesan .= "Total Bayar: Rp{$total}\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan Bintang Rental Motor.";

        try {
            $fonnte = app(\App\Services\FonnteService::class);
            $result = $fonnte->sendMessage($nomor, $pesan);

            // Log hasil response Fonnte untuk debug
            \Log::info('Fonnte WA result', [
                'nomor'   => $nomor,
                'metode'  => $metode,
                'response' => $result,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Fonnte gagal kirim WA: ' . $e->getMessage());
        }
    }
}