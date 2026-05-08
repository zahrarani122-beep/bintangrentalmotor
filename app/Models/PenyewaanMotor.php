<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenyewaanMotor extends Model
{
    use HasFactory;

    // nama tabel
    protected $table = 'penyewaan_motor';

    // mass assignment
    protected $guarded = [];

    /**
     * Relasi ke tabel penyewaan
     */
    public function penyewaan()
    {
        return $this->belongsTo(
            Penyewaan::class,
            'penyewaan_id', // foreign key di tabel penyewaan_motor
            'id_sewa'       // primary key di tabel penyewaan
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
     * Hitung subtotal otomatis
     */
    protected static function booted()
    {
        static::saving(function ($penyewaanMotor) {

            $penyewaanMotor->subtotal =
                $penyewaanMotor->harga_sewa *
                $penyewaanMotor->jml;
        });
    }
}