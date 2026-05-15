<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class akun extends Model
{
    use HasFactory;
    protected $table = 'akun'; //nama tabel eksplisit

    // seluruh kolom dapat dimodifikasi
    protected $guarded = [];
    
    // Model Akun — satu akun HANYA PUNYA SATU pencatatan biaya
    public function pencatatanBiaya()
{
    return $this->hasOne(PencatatanBiaya::class, 'akun_id', 'id');
}
}

