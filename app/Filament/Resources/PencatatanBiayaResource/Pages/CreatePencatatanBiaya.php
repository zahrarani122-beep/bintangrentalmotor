<?php

namespace App\Filament\Resources\PencatatanBiayaResource\Pages;

use App\Filament\Resources\PencatatanBiayaResource;
use App\Models\Akun;
use App\Models\PencatatanBiaya;
use Filament\Resources\Pages\CreateRecord;

class CreatePencatatanBiaya extends CreateRecord
{
    protected static string $resource = PencatatanBiayaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['kode_pencatatan'] = PencatatanBiaya::generateKode($data['tanggal_catat'] ?? null);
        $data['jenis_beban'] = Akun::find($data['akun_id'])?->nama_akun ?? 'Beban';

        return $data;
    }
}
