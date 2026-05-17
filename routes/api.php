<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// ================================================================
// TAMBAHKAN KE routes/api.php
// (bebas CSRF — dibutuhkan agar Midtrans bisa POST ke webhook kita)
// ================================================================

Route::post('/midtrans/callback', [App\Http\Controllers\CobaMidtransController::class, 'handleCallback'])
    ->name('midtrans.callback');