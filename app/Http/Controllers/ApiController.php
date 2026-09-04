<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Agency\Models\Agency;
use Modules\Association\Models\Association;
use Modules\Customer\Models\Customer;
use Modules\Surveyor\Models\Surveyor;

/**
 * Endpoint sistem: health, login, dan user.
 *
 * @group api/v1
 * @subgroup System
 */
class ApiController extends Controller
{
    /** Relasi yang disertakan utk entity HO/cabang (company & branches). */
    private const ENTITY_WITH = [
        'vat:id,npwp,name',
        'province:id,name',
        'regency:id,name',
        'admin:id,name',
    ];

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

    /**
     * Company + branches milik user yang login (tab My Company / My Branch).
     *
     * Resolusi entity sama dengan pola ActorResolver (Connection): user = admin
     * entity (customers.admin_id / surveyors.admin_id) — mencakup HO maupun
     * cabang. Branch = row type='branch' dgn parent_id -> HO.
     *
     * @authenticated
     *
     * @response scenario="user di HO" {
     *   "type": "customer",
     *   "company": { "id": 57, "code": "ALPHA", "type": "customer" },
     *   "entity": { "id": 57, "code": "ALPHA", "type": "customer", "parent_id": null },
     *   "branches": [ { "id": 78, "code": "A01", "type": "branch" } ]
     * }
     */
    public function company(Request $request): JsonResponse
    {
        $user = $request->user();

        $customer = Customer::where('admin_id', $user->id)->first();
        if ($customer) {
            return response()->json($this->companyPayload('customer', $customer));
        }

        $surveyor = Surveyor::where('admin_id', $user->id)->first();
        if ($surveyor) {
            return response()->json($this->companyPayload('surveyor', $surveyor));
        }

        return response()->json(['type' => null, 'company' => null, 'entity' => null, 'branches' => []]);
    }

    /**
     * Entity tempat user yang SEDANG LOGIN bernaung (banner "logged as").
     *
     * Resolve by user id (admin_id) lintas 4 world entity — berlaku utk siapa
     * pun pemegang token (admin asli maupun hasil impersonate).
     *
     * @authenticated
     *
     * @response scenario=success {
     *   "type": "customer",
     *   "entity": { "id": 78, "code": "A01", "name": "Cabang Jawa Barat", "type": "branch" }
     * }
     */
    public function entity(Request $request): JsonResponse
    {
        $user = $request->user();

        $worlds = [
            'customer'    => Customer::where('admin_id', $user->id)->first(),
            'surveyor'    => Surveyor::where('admin_id', $user->id)->first(),
            'agency'      => Agency::where('admin_id', $user->id)->first(),
            'association' => Association::where('admin_id', $user->id)->first(),
        ];

        foreach ($worlds as $type => $entity) {
            if ($entity) {
                return response()->json([
                    'type'   => $type,
                    'entity' => [
                        'id'        => $entity->id,
                        'code'      => $entity->code ?? null,
                        'name'      => $entity->name,
                        'type'      => $entity->type ?? $type,
                        'parent_id' => $entity->parent_id ?? null,
                    ],
                ]);
            }
        }

        return response()->json(['type' => null, 'entity' => null]);
    }

    /**
     * Susun payload company utk satu world (customer/surveyor).
     *
     * @param  'customer'|'surveyor'  $type
     * @param  Customer|Surveyor  $entity  row entity user (HO atau cabang)
     */
    private function companyPayload(string $type, $entity): array
    {
        $model = $type === 'customer' ? Customer::class : Surveyor::class;

        // HO = row type HO; kalau user di cabang, HO = parent-nya.
        $company = $entity->type === 'branch'
            ? $model::with(self::ENTITY_WITH)->find($entity->parent_id)
            : $entity->load(self::ENTITY_WITH);

        // Cabang milik user: HO -> semua children; user cabang -> dirinya sendiri.
        $branches = $entity->type === 'branch'
            ? collect([$entity->fresh(self::ENTITY_WITH)])
            : $model::where('parent_id', $entity->id)->where('type', 'branch')->with(self::ENTITY_WITH)->orderBy('id')->get();

        return [
            'type'     => $type,
            'company'  => $company,
            'entity'   => $entity->only(['id', 'code', 'name', 'type', 'parent_id']),
            'branches' => $branches,
        ];
    }
}
