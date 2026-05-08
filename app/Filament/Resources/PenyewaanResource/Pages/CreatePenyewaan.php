<?php

namespace App\Filament\Resources\PenyewaanResource\Pages;

use App\Filament\Resources\PenyewaanResource;

use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

// models
use App\Models\Penyewaan;
use App\Models\PenyewaanMotor;
use App\Models\Motor;

// notification
use Filament\Notifications\Notification;

class CreatePenyewaan extends CreateRecord
{
    protected static string $resource = PenyewaanResource::class;

    /**
     * Sebelum create
     */
    protected function beforeCreate(): void
    {
        //
    }

    /**
     * Tombol action form
     */
    protected function getFormActions(): array
    {
        return [

            Actions\Action::make('simpan')
                ->label('Simpan Penyewaan')
                ->color('success')
                ->action(fn () => $this->simpanPenyewaan())
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Penyewaan')
                ->modalDescription('Apakah data penyewaan sudah benar?')
                ->modalButton('Ya, Simpan'),

        ];
    }

    /**
     * Simpan penyewaan
     */
    protected function simpanPenyewaan(): void
    {
        // ambil penyewaan terbaru
        $penyewaan = $this->record ?? Penyewaan::latest()->first();

        // ambil semua detail motor
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

        // notifikasi sukses
        Notification::make()
            ->title('Penyewaan Berhasil Disimpan!')
            ->success()
            ->send();
    }
}