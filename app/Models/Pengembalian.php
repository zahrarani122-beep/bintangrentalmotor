<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengembalian extends Model
{
    use HasFactory;

    protected $table = 'pengembalian';

    protected $primaryKey = 'id_pengembalian';

    protected $fillable = [
        'id_sewa',
        'tgl_pengembalian',
        'denda',
        'keterangan',
    ];

    protected $casts = [
        'tgl_pengembalian' => 'date',
    ];

    public function penyewaan()
    {
        return $this->belongsTo(Penyewaan::class, 'id_sewa', 'id_sewa');
    }
}