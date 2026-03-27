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
        Schema::create('motor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_motor'); 
            $table->string('jenis_motor'); 
            $table->string('merek_motor'); 
            $table->string('plat_nomor'); 
            $table->string('foto_motor'); 
            $table->enum('status', ['tersedia', 'disewa'])->default('tersedia'); 
            $table->integer('harga_sewa_perhari');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motor');
    }
};
