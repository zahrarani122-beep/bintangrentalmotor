<?php

namespace App\Filament\Resources\BukuBesarResource\Pages;

use App\Filament\Resources\BukuBesarResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBukuBesar extends EditRecord
{
    protected static string $resource = BukuBesarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
