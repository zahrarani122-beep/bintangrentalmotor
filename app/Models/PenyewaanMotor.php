<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenyewaanMotor extends Model
{
    use HasFactory;

    protected $table   = 'penyewaan_motor';
    protected $guarded = [];

    /**
     * Relasi ke tabel penyewaan
     */
    public function penyewaan()
    {
        return $this->belongsTo(
            Penyewaan::class,
            'penyewaan_id',
            'id_sewa'
        );
    }

    /**
     * Relasi ke tabel motor
     */
    public function motor()
    {
        return $this->belongsTo(
            Motor::class,
            'motor_id',
            'id'
        );
    }

    /**
     * Hitung subtotal otomatis saat saving
     */
    protected static function booted()
    {
        static::saving(function ($penyewaanMotor) {
            $penyewaanMotor->subtotal =
                $penyewaanMotor->harga_sewa_perhari *  // ✅ fix nama kolom
                $penyewaanMotor->jml;
        });
    }
}