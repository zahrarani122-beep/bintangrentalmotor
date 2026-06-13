<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Penyewaan extends Model
{
    use HasFactory;

    protected $table      = 'penyewaan';
    protected $primaryKey = 'id_sewa';
    public    $incrementing = true;
    protected $keyType    = 'int';

    // ✅ Hanya kolom ini yang disimpan ke DB
    protected $fillable = [
    'pelanggan_id',
    'no_faktur',
    'tgl_sewa',
    'tgl_kembali',
    'total_harga',
    'metode',
    'bukti_bayar',
    'tgl_bayar',
    'status_bayar',
];

    /**
     * Generate kode faktur otomatis
     */
    public static function getKodeFaktur()
    {
        $sql = "SELECT IFNULL(MAX(no_faktur), 'S-0000000') as no_faktur 
                FROM penyewaan";

        $kodefaktur = DB::select($sql);

        foreach ($kodefaktur as $kdfk) {
            $kd = $kdfk->no_faktur;
        }

        $noawal  = substr($kd, -7);
        $noakhir = $noawal + 1;
        $noakhir = 'S-' . str_pad($noakhir, 7, "0", STR_PAD_LEFT);

        return $noakhir;
    }

    /**
     * Total sewa dihitung dinamis dari relasi
     * Tidak perlu kolom total_harga di DB
     */
    public function getTotalHargaAttribute(): float
    {
        return $this->penyewaanMotor->sum('subtotal');
    }

    /**
     * Relasi ke pelanggan
     */
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    /**
     * Relasi ke tabel penyewaan_motor
     */
    public function penyewaanMotor()
    {
        return $this->hasMany(
            PenyewaanMotor::class,
            'penyewaan_id',
            'id_sewa'
        );
    }

    /**
     * Relasi ke pengembalian
     */
    public function pengembalian()
    {
        return $this->hasOne(Pengembalian::class, 'id_sewa', 'id_sewa');
    }

    public function pembayaran()
{
    return $this->hasOne(Pembayaran::class, 'penyewaan_id', 'id_sewa');
}
}