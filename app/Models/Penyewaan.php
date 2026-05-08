<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// tambahan DB
use Illuminate\Support\Facades\DB;

class Penyewaan extends Model
{
    use HasFactory;

    // nama tabel
    protected $table = 'penyewaan';

    // primary key
    protected $primaryKey = 'id_sewa';

    // mass assignment
    protected $guarded = [];

    /**
     * Generate kode faktur otomatis
     */
    public static function getKodeFaktur()
    {
        // query kode faktur terakhir
        $sql = "SELECT IFNULL(MAX(no_faktur), 'S-0000000') as no_faktur 
                FROM penyewaan";

        $kodefaktur = DB::select($sql);

        // ambil hasil
        foreach ($kodefaktur as $kdfk) {
            $kd = $kdfk->no_faktur;
        }

        // ambil 7 digit terakhir
        $noawal = substr($kd, -7);

        // tambah 1
        $noakhir = $noawal + 1;

        // format ulang
        $noakhir = 'S-' . str_pad($noakhir, 7, "0", STR_PAD_LEFT);

        return $noakhir;
    }

    /**
     * Relasi ke pelanggan
     */
    public function pelanggan()
    {
        return $this->belongsTo(
            Pelanggan::class,
            'pelanggan_id'
        );
    }

    /**
     * Relasi ke tabel penyewaan_motor
     */
    public function penyewaanMotor()
    {
        return $this->hasMany(
            PenyewaanMotor::class,
            'penyewaan_id', // FK di tabel penyewaan_motor
            'id_sewa'       // PK tabel penyewaan
        );
    }
}