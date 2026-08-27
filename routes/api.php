<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\MetaController;
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

    // Custom Meta (polymorphic, key-value per entity)
    Route::get("/meta/{type}/{id}", [MetaController::class, "index"]);
    Route::post("/meta/{type}/{id}", [MetaController::class, "store"]);
    Route::get("/meta/{type}/{id}/{key}", [MetaController::class, "show"]);
    Route::put("/meta/{type}/{id}/{key}", [MetaController::class, "update"]);
    Route::delete("/meta/{type}/{id}/{key}", [MetaController::class, "destroy"]);
});
