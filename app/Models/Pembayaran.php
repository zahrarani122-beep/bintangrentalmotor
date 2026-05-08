<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    use HasFactory;

    // nama tabel
    protected $table = 'pembayaran';

    // primary key
    protected $primaryKey = 'id_pembayaran';

    // mass assignment
    protected $guarded = [];

    /**
     * Relasi ke tabel penyewaan
     */
    public function penyewaan()
    {
        return $this->belongsTo(
            Penyewaan::class,
            'penyewaan_id', // foreign key di tabel pembayaran
            'id_sewa'       // primary key di tabel penyewaan
        );
    }

    /**
     * Format total harga otomatis
     */
    public function setTotalHargaAttribute($value)
    {
        $this->attributes['total_harga'] =
            str_replace('.', '', $value);
    }
}