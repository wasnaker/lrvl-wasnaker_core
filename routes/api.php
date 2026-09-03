<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth + user (app-specific; infrastruktur generik ada di package spine).
Route::prefix('v1')->group(function () {

    Route::get('/health', [ApiController::class, 'health']);

    Route::name('login')->get('/login', [ApiController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [ApiController::class, 'user']);
        Route::put('/user', [ApiController::class, 'updateProfile']);
        Route::post('/user/avatar', [ApiController::class, 'uploadAvatar']);
        Route::delete('/user/avatar', [ApiController::class, 'destroyAvatar']);

        // ── User management (akun internal; domain aplikasi, bukan modul) ──
        Route::middleware('permission:users:view')->get('/users', [UserController::class, 'index']);
        Route::middleware('permission:users:view')->get('/users/{id}', [UserController::class, 'show']);
        Route::middleware('permission:users:create')->post('/users', [UserController::class, 'store']);
        Route::middleware('permission:users:edit')->put('/users/{id}', [UserController::class, 'update']);
        Route::middleware('permission:users:delete')->delete('/users/{id}', [UserController::class, 'destroy']);

        // ── Role & permission (domain aplikasi) ──
        // index roles: roles:view (halaman Roles) ATAU users:edit (assign role di form user).
        Route::middleware('permission:roles:view|users:edit')->get('/roles', [RoleController::class, 'index']);
        Route::middleware('permission:roles:view')->get('/permissions', [RoleController::class, 'permissions']);
        Route::middleware('permission:roles:view')->get('/roles/{id}', [RoleController::class, 'show']);
        Route::middleware('permission:roles:create')->post('/roles', [RoleController::class, 'store']);
        Route::middleware('permission:roles:edit')->put('/roles/{id}', [RoleController::class, 'update']);
        Route::middleware('permission:roles:delete')->delete('/roles/{id}', [RoleController::class, 'destroy']);
    });
});
