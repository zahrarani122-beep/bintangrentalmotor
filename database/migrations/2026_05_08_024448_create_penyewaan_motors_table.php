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
        Schema::create('penyewaan_motor', function (Blueprint $table) {
            $table->id();
            // relasi ke penyewaan
            $table->foreignId('penyewaan_id')->references('id_sewa')->on('penyewaan')->onDelete('cascade');
            // relasi ke motor
            $table->foreignId('motor_id')->constrained('motor')->onDelete('cascade');
            // detail transaksi
            $table->decimal('harga_sewa_perhari', 15, 2);
            $table->integer('jml')->default(1);
            $table->date('tgl');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penyewaan_motor');
    }
};