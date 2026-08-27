<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'service' => 'wasnaker-api',
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]);
});

Route::name('login')->get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
