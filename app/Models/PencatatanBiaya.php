<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PencatatanBiaya extends Model
{
    use HasFactory;

    protected $table = 'pencatatan_biaya';

    protected $primaryKey = 'id_pencatatan_beban';

    protected $fillable = [
        'jenis_beban',
        'nominal',
        'tanggal_catat',
        'keterangan',
        'tanggal_catat',
        'nominal',
    ];
}