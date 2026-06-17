<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BukuBesar extends Model
{
    use HasFactory;

    // dialihkan ke tabel jurnal krn buku besar tidak memerlukan tabel
    protected $table = 'jurnal'; // Nama tabel eksplisit

    // // relasi ke jurnal detail
    public function jurnaldetail()
    {
        return $this->hasMany(JurnalDetail::class, 'jurnal_id');
    }
}