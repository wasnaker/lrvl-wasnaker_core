<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Endpoint sistem: health, login, dan user.
 *
 * @group api/v1
 * @subgroup System
 */
class ApiController extends Controller
{
    /**
     * Endpoint login (belum terautentikasi).
     *
     * Path tujuan saat klien belum terautentikasi (route bernama `login`).
     *
     * @response 401 {"message": "Unauthenticated."}
     */
    public function login(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    /**
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

    /**
     * Update profil user yang sedang login (self-service).
     *
     * Hanya name (data akun) dan/atau current_password + password +
     * password_confirmation (ganti sandi). Email TIDAK bisa diubah sendiri —
     * itu domain admin (UserController, permission users:edit).
     *
     * @authenticated
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);

        if ($request->has('password')) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return response()->json(['message' => 'Current password is incorrect.'], 422);
            }
            $user->password = $validated['password'];
        }

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        // Email TIDAK bisa diubah user sendiri — domain admin (UserController).

        $user->save();

        return response()->json($user);
    }

    /**
     * Upload avatar user yang sedang login (multipart, disk public).
     *
     * File disimpan di storage/app/public/avatars/ — disajikan nginx
     * via /storage/avatars/... (tanpa auth, memang publik seperti foto profil).
     *
     * @authenticated
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = $validated['avatar']->store('avatars', 'public');
        $user->save();

        return response()->json($user);
    }

    /**
     * Hapus avatar user yang sedang login.
     *
     * @authenticated
     */
    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->avatar = null;
            $user->save();
        }

        return response()->json($user);
    }
}
