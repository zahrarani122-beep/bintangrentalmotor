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

            $table->enum('denda', [
                'Keterlambatan Pengembalian',
                'Kehilangan',
                'Kerusakan'
            ])->nullable();

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