<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CobaMidtransController;

Route::get('/', function () {
    return view('welcome');
});

// ================================================================
// TAMBAHKAN KE routes/web.php
// ================================================================
// Midtrans - generate snap token (butuh CSRF, dipanggil dari Filament admin)
Route::post('/midtrans/snap-token', [App\Http\Controllers\CobaMidtransController::class, 'getSnapToken'])
    ->name('midtrans.snap-token');

// Cek status pembayaran manual (opsional, bisa diakses dari browser admin)
Route::get('/midtrans/cek-status', [App\Http\Controllers\CobaMidtransController::class, 'cekStatus'])
    ->name('midtrans.cek-status');