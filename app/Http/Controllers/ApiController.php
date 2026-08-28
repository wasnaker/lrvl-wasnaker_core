<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    /**
     * Endpoint login (belum terautentikasi).
     *
     * Path tujuan saat klien belum terautentikasi (route bernama `login`).
     *
     * @group api/v1
     * @subgroup System
     *
     * @response 401 {"message": "Unauthenticated."}
     */
    public function login(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    /**
     * @group api/v1
     * @subgroup System
     *
     * Cek kesehatan API.
     *
     * Mengecek apakah layanan API berjalan dengan baik.
     *
     * @response {
     *   "service": "wasnaker-api",
     *   "status": "ok",
     *   "time": "2026-08-27T00:00:00+00:00"
     * }
     */
    public function health(): JsonResponse
    {
        return response()->json([
            "service" => "wasnaker-api",
            "status" => "ok",
            "time" => now()->toIso8601String(),
        ]);
    }

    /**
     * @group api/v1
     * @subgroup System
     *
     * Data user yang sedang login.
     *
     * Mengembalikan data user terkini berdasarkan token Sanctum yang terkirim.
     *
     * @authenticated
     *
     * @response scenario=success {
     *   "id": 1,
     *   "name": "Admin",
     *   "email": "admin@wasnaker.lan"
     * }
     * @response status=401 scenario="tidak terautentikasi" {
     *   "message": "Unauthenticated."
     * }
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
