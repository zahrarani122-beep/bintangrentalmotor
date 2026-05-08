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
        Schema::create('pengembalian', function (Blueprint $table) {
            $table->id('id_pengembalian');

            $table->unsignedBigInteger('id_sewa');

            $table->date('tgl_pengembalian');

            // Pilihan utama: ada denda atau tidak
            $table->enum('denda', [
                'Ada Denda',
                'Tidak Ada Denda'
            ])->default('Tidak Ada Denda');

            // Untuk menyimpan banyak denda
            // Contoh:
            // [
            //   {"jenis_denda": "Kehilangan", "nama_denda": "Helm", "nominal": 50000},
            //   {"jenis_denda": "Kerusakan", "nama_denda": "Motor", "nominal": 150000}
            // ]
            $table->json('detail_denda')->nullable();

            // Total seluruh nominal denda
            $table->decimal('total', 12, 2)->default(0);

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->foreign('id_sewa')
                ->references('id_sewa')
                ->on('penyewaan')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengembalian');
    }
};