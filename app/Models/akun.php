<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Akun extends Model
{
    use HasFactory;

    protected $table = 'akun';

    protected $guarded = [];

    public function pencatatanBiaya(): HasMany
    {
        return $this->hasMany(PencatatanBiaya::class, 'akun_id', 'id');
    }
}
