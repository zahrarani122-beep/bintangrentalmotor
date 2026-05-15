<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pencatatan_biaya', function (Blueprint $table) {
            $table->string('kode_pencatatan')
                ->nullable()
                ->after('id_pencatatan_beban');

            $table->unique(['tanggal_catat', 'kode_pencatatan'], 'pencatatan_biaya_tanggal_kode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pencatatan_biaya', function (Blueprint $table) {
            $table->dropUnique('pencatatan_biaya_tanggal_kode_unique');
            $table->dropColumn('kode_pencatatan');
        });
    }
};
