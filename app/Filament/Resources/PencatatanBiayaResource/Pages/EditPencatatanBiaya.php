<?php

namespace App\Filament\Resources\PencatatanBiayaResource\Pages;

use App\Filament\Resources\PencatatanBiayaResource;
use App\Models\Akun;
use App\Models\PencatatanBiaya;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPencatatanBiaya extends EditRecord
{
    protected static string $resource = PencatatanBiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['kode_pencatatan'] = $data['kode_pencatatan']
            ?? PencatatanBiaya::generateKode($data['tanggal_catat'] ?? null);
        $data['jenis_beban'] = Akun::find($data['akun_id'])?->nama_akun ?? ($data['jenis_beban'] ?? 'Beban');

        return $data;
    }
}
