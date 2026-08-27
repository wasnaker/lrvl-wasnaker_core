<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get("/up", function () {
    try {
        DB::select("select 1");
    } catch (\Throwable $e) {
        return response()->json(["status" => "degraded", "database" => "down"], 503);
    }
    return response()->json(["status" => "ok"]);
});

Route::get("/", function () {
    return response()->file(public_path("index.html"));
});

Route::get("/{any}", function () {
    return response()->file(public_path("index.html"));
})->where("any", "^(?!api(?:/|$)|up(?:/|$)|_ignition(?:/|$)).*");
