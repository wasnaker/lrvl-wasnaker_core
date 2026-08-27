<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Konvensi respons API Wasnaker (kontrak konsisten untuk semua endpoint).
 *
 * Bentuk envelope:
 *  - List/index  : { "data": [...], "meta": { "count", "per_page", "page" } }
 *  - Single      : { "data": {...} }
 *  - Created     : { "data": {...} } status 201
 *  - No content  : status 204 (kosong)
 *  - Error       : { "message": "...", "errors": { field: [...] } } (Laravel default 422)
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(["data" => $data], $status);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return response()->json(["data" => $data], 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function list(mixed $items = [], int $count = 0, int $perPage = 0, int $page = 1): JsonResponse
    {
        return response()->json([
            "data" => $items,
            "meta" => [
                "count" => $count,
                "per_page" => $perPage,
                "page" => $page,
            ],
        ]);
    }
}
