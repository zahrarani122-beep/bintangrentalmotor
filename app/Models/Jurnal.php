<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $table = 'jurnal';
    protected $fillable = [
    'tgl',
    'no_referensi',
    'deskripsi',
];
    use HasFactory;

    // relasi ke jurnal detail 1-N
    public function jurnaldetail()
    {
        return $this->hasMany(JurnalDetail::class);
    }

}
