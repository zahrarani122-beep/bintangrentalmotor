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
        'detail_denda',
        'total',
        'keterangan',
    ];

    protected $casts = [
        'tgl_pengembalian' => 'date',
        'detail_denda' => 'array',
        'total' => 'decimal:2',
    ];

    public function penyewaan()
    {
        return $this->belongsTo(Penyewaan::class, 'id_sewa', 'id_sewa');
    }
}