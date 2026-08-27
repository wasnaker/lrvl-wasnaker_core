<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::get("/health", [ApiController::class, "health"]);

Route::name("login")->get("/login", function () {
    return response()->json(["message" => "Unauthenticated."], 401);
});

Route::middleware("auth:sanctum")->group(function () {
    Route::get("/user", [ApiController::class, "user"]);

    // Settings (key-value API, bukan CRUD klasik)
    Route::get("/settings/{key}", [SettingController::class, "show"]);
    Route::put("/settings/{key}", [SettingController::class, "upsert"]);
    Route::delete("/settings/{key}", [SettingController::class, "destroy"]);
    Route::post("/settings/bulk", [SettingController::class, "bulk"]);

    // Activity Logs (resource REST, multi-tenant)
    Route::apiResource("activity-logs", ActivityLogController::class)->only([
        "index", "show", "store", "destroy"
    ]);
});
