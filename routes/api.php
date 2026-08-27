<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::get("/health", [ApiController::class, "health"]);

Route::name("login")->get("/login", function () {
    return response()->json(["message" => "Unauthenticated."], 401);
});

Route::middleware("auth:sanctum")->get("/user", [ApiController::class, "user"]);
