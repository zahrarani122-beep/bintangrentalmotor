<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penyewaan', function (Blueprint $table) {
            // Hapus durasi_sewa karena sudah dipindah ke tabel penyewaan_motor
            $table->dropColumn('durasi_sewa');

            // Tambah kolom yang belum ada
            $table->decimal('total_harga', 15, 2)->default(0)->after('tgl_kembali');
            $table->string('metode')->nullable()->after('total_harga');
            $table->string('bukti_bayar')->nullable()->after('metode');
            $table->date('tgl_bayar')->nullable()->after('bukti_bayar');
        });
    }

    public function down(): void
    {
        Schema::table('penyewaan', function (Blueprint $table) {
            $table->integer('durasi_sewa');
            $table->dropColumn([
                'total_harga',
                'metode',
                'bukti_bayar',
                'tgl_bayar',
            ]);
        });
    }
};