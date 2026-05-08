<?php

namespace App\Filament\Resources\PenyewaanResource\Pages;

use App\Filament\Resources\PenyewaanResource;

use Filament\Resources\Pages\CreateRecord;

// models
use App\Models\PenyewaanMotor;
use App\Models\Motor;

// notification
use Filament\Notifications\Notification;

class CreatePenyewaan extends CreateRecord
{
    protected static string $resource = PenyewaanResource::class;

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

        // notifikasi sukses
        Notification::make()
            ->title('Penyewaan Berhasil Disimpan!')
            ->success()
            ->send();
    }
}