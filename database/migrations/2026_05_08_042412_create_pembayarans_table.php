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
        Schema::create('pembayaran', function (Blueprint $table) {

            // primary key
            $table->id('id_pembayaran');

            // relasi ke penyewaan
            $table->foreignId('penyewaan_id')
                ->references('id_sewa')
                ->on('penyewaan')
                ->onDelete('cascade');

            // tanggal pembayaran
            $table->date('tgl_bayar');

            // metode pembayaran
            $table->string('metode');

            // waktu transaksi
            $table->dateTime('transaction_time')->nullable();

            // total pembayaran
            $table->decimal('total_harga', 15, 2);

            // bukti pembayaran
            $table->string('bukti_bayar')->nullable();

            // tambahan transaksi pembayaran
            $table->string('order_id')->nullable();

            $table->string('payment_type')->nullable();

            $table->string('status_code')->nullable();

            $table->string('transaction_id')->nullable();

            $table->dateTime('settlement_time')->nullable();

            $table->string('status_message')->nullable();

            $table->string('merchant_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};