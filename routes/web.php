<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fallback rute forgot-password jika reverse-proxy Nginx memotong prefix /api
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
Route::post('/api/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
