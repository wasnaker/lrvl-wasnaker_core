<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth + user (app-specific; infrastruktur generik ada di package spine).
Route::prefix('v1')->group(function () {

    Route::get('/health', [ApiController::class, 'health']);

    Route::name('login')->get('/login', [ApiController::class, 'login']);

    // Registrasi publik (self-registration, tanpa auth).
    Route::post('/register', [RegistrationController::class, 'store']);
    Route::post('/register/verify', [RegistrationController::class, 'verify']);
    Route::post('/register/resend', [RegistrationController::class, 'resend']);

    Route::middleware('auth:sanctum')->group(function () {
        // Get list of supported locales
        Route::get('/locales', function () {
            return response()->json(['locales' => config('app.available_locales')]);
        });
        // Update authenticated user's locale (self-service, tanpa permission khusus)
        Route::put('/user/locale', function (\Illuminate\Http\Request $request) {
            $locale = $request->input('locale');
            if (! in_array($locale, config('app.available_locales'), true)) {
                return response()->json(['message' => __('messages.invalid_locale')], 422);
            }
            $user = $request->user();
            $user->locale = $locale;
            $user->save();
            return response()->json(['message' => __('messages.saved'), 'locale' => $locale]);
        });

        Route::get('/user', [ApiController::class, 'user']);
        Route::put('/user', [ApiController::class, 'updateProfile']);
        Route::get('/user/company', [ApiController::class, 'company']);
        Route::put('/user/company', [ApiController::class, 'updateCompany']);
        Route::post('/user/company/npwp-claim', [ApiController::class, 'claimNpwp']);
        Route::post('/user/company/npwp-file', [ApiController::class, 'uploadNpwpFile']);
        Route::get('/user/company/npwp-file', [ApiController::class, 'downloadNpwpFile']);
        Route::get('/user/entity', [ApiController::class, 'entity']);
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
