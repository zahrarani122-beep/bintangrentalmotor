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
        Schema::create('pencatatan_biaya', function (Blueprint $table) {
            $table->id('id_pencatatan_beban');
            $table->string('jenis_beban');
            $table->decimal('nominal', 15, 2);
            $table->date('tanggal_catat');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pencatatan_biaya');
    }
};