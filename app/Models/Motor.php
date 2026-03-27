<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Motor extends Model
{
    use HasFactory;

    protected $table = 'motor';

    protected $guarded = [];

    public static function getKodeMotor()
    {
        $sql = "SELECT IFNULL(MAX(plat_nomor), 'MT000') as plat_nomor FROM motor";
        $kodemotor = DB::select($sql);

        foreach ($kodemotor as $kdmtr) {
            $kd = $kdmtr->plat_nomor;
        }

        $noawal = substr($kd, -3);
        $noakhir = $noawal + 1;
        $noakhir = 'MT' . str_pad($noakhir, 3, "0", STR_PAD_LEFT);

        return $noakhir;
    }

    public function setHargaSewaPerhariAttribute($value)
    {
        $this->attributes['harga_sewa_perhari'] = str_replace('.', '', $value);
    }
}