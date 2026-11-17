<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fallback rute forgot-password jika reverse-proxy Nginx memotong prefix /api
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:30,1');
Route::post('/api/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:30,1');

// Fallback rute reset-password untuk admin jika dipanggil via web router
Route::middleware('auth:sanctum')->group(function () {
    Route::match(['put', 'post'], '/users/{id}/reset-password', [AuthController::class, 'resetUserPassword'])->where('id', '.*');
    Route::match(['put', 'post'], '/api/users/{id}/reset-password', [AuthController::class, 'resetUserPassword'])->where('id', '.*');
    Route::match(['put', 'post'], '/users/reset-password', [AuthController::class, 'resetUserPassword']);
    Route::match(['put', 'post'], '/api/users/reset-password', [AuthController::class, 'resetUserPassword']);
});
