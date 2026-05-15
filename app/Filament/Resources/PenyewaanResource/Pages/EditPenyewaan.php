<?php

namespace App\Filament\Resources\PenyewaanResource\Pages;

use App\Filament\Resources\PenyewaanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

// notification
use Filament\Notifications\Notification;

class EditPenyewaan extends EditRecord
{
    protected static string $resource = PenyewaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Hitung ulang total saat edit disimpan
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
     * Notifikasi setelah edit disimpan
     */
    protected function afterSave(): void
    {
        Notification::make()
            ->title('Penyewaan Berhasil Diperbarui!')
            ->success()
            ->send();
    }
}