<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// Auth + user (app-specific; infrastruktur generik ada di package spine).
Route::prefix('v1')->group(function () {

    Route::get('/health', [ApiController::class, 'health']);

    Route::name('login')->get('/login', [ApiController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [ApiController::class, 'user']);
    });
});
