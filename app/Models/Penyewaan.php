<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penyewaan extends Model
{
    use HasFactory;
    public function pengembalian()
    {
        return $this->hasOne(Pengembalian::class, 'id_sewa', 'id_sewa');
    }
}