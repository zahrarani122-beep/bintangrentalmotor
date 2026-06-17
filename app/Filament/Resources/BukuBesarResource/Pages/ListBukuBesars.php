<?php

namespace App\Filament\Resources\BukuBesarResource\Pages;

use App\Filament\Resources\BukuBesarResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

// tambahan ke widget BukuBesar
use App\Filament\Resources\BukuBesarResource\Widgets\BukuBesar;

class ListBukuBesars extends ListRecords
{
    protected static string $resource = BukuBesarResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
            
    //         // Actions\CreateAction::make(),
    //     ];
    // }

    // tambahan method untuk menampilkan widgetnya
    protected function getHeaderWidgets(): array
    {
        return [
            BukuBesar::class,
        ];
    }
}