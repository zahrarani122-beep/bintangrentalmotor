<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pelanggan extends Model
{
    use HasFactory;
    protected $table = 'pelanggan';
    protected $guarded = [];

    public static function generateKodePelanggan()
    {
        $sql = "SELECT IFNULL(MAX(kode_pelanggan), 'PLG000') as kode_pelanggan FROM pelanggan";
        $kodepelanggan = DB::select($sql);

        foreach ($kodepelanggan as $kdp) {
            $kd = $kdp->kode_pelanggan;
        }

        $noawal = substr($kd, -3);
        $noakhir = $noawal + 1;
        $noakhir = 'PLG' . str_pad($noakhir, 3, "0", STR_PAD_LEFT);

        return $noakhir;
    }
}
