<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PencatatanBiaya extends Model
{
    use HasFactory;

    protected $table = 'pencatatan_biaya';

    protected $primaryKey = 'id_pencatatan_beban';

    protected $fillable = [
        'kode_pencatatan',
        'akun_id',
        'jenis_beban',
        'nominal',
        'tanggal_catat',
        'keterangan',
    ];

    public static function generateKode($tanggalCatat = null): string
    {
        $tanggalCatat = $tanggalCatat
            ? Carbon::parse($tanggalCatat)->toDateString()
            : now()->toDateString();

        $lastKode = self::query()
            ->whereDate('tanggal_catat', $tanggalCatat)
            ->whereNotNull('kode_pencatatan')
            ->orderByDesc('kode_pencatatan')
            ->value('kode_pencatatan');

        $nextNumber = $lastKode
            ? ((int) substr($lastKode, 3)) + 1
            : 1;

        return 'PBY' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id', 'id');
    }
}
