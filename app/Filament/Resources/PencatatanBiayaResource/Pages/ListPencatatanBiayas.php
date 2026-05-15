<?php

namespace App\Filament\Resources\PencatatanBiayaResource\Pages;

use App\Filament\Resources\PencatatanBiayaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPencatatanBiayas extends ListRecords
{
    protected static string $resource = PencatatanBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
