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
            $table->foreignId('akun_id')
                ->nullable()
                ->after('id_pencatatan_beban')
                ->constrained('akun')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pencatatan_biaya', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_id');
        });
    }
};
