<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\EventController;

/*
|--------------------------------------------------------------------------
| ROUTE MODUL (konvensi core: api/v1 + auth:sanctum)
|--------------------------------------------------------------------------
|   GET    /api/v1/events              (list)
|   POST   /api/v1/events
|   GET    /api/v1/events/{id}
|   PUT    /api/v1/events/{id}
|   GET    /api/v1/events/{id}/activity-logs
|   DELETE /api/v1/events/{id}
*/

Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{id}', [EventController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [EventController::class, 'update'])->whereNumber('id');
        Route::get('/{id}/activity-logs', [EventController::class, 'activityLogs'])->whereNumber('id');
        Route::delete('/{id}', [EventController::class, 'destroy'])->whereNumber('id');
    });
});
