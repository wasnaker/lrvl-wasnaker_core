<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Agency\Models\Agency;
use Modules\Association\Models\Association;
use Modules\Customer\Models\Customer;
use Modules\Surveyor\Models\Surveyor;
use Modules\Vat\Models\Vat;
use Modules\Vat\Services\VatService;

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
        'vat:id,npwp,name,address,province_id,regency_id,postal_code,owner_id',
        'vat.province:id,name',
        'vat.regency:id,name',
        'vat.owner:id,name',
        'province:id,name',
        'regency:id,name',
        'admin:id,name',
    ];

    /** True kalau user boleh kelola data vat (owner, atau admin platform). */
    private function canManageVat(User $user, ?Vat $vat): bool
    {
        if (! $vat) {
            return false;
        }
        if ($vat->owner_id === null || $vat->owner_id === $user->id) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'platform-admin']);
    }

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
        return response()->json($request->user()->append('access'));
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
     * Edit data entity sendiri (self-service dari My Company profile).
     *
     * Hanya untuk user yang menjadi admin entity (admin_id). Field: identitas
     * umum (name/email/phone/address/provinsi/kabupaten) + NIB KHUSUS HO
     * (cabang ikut NIB HO — keputusan NIB 2026-09-10). NPWP tetap via modul
     * (VatService), tidak di sini.
     */
    public function updateCompany(Request $request): JsonResponse
    {
        $user = $request->user();

        $entity = Customer::where('admin_id', $user->id)->first()
            ?? Surveyor::where('admin_id', $user->id)->first();

        if (! $entity) {
            return response()->json(['message' => 'Akun tidak terikat ke company mana pun.'], 404);
        }

        $isBranch = $entity->type === 'branch';

        if ($isBranch && $request->has('nib')) {
            return response()->json(['message' => 'NIB hanya untuk kantor pusat.'], 422);
        }

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:190'],
            'email'            => ['nullable', 'string', 'email', 'max:190'],
            'phone'            => ['nullable', 'string', 'max:32'],
            'address'          => ['nullable', 'string'],
            'postal_code'      => ['nullable', 'string', 'max:10'],
            'province_id'      => ['nullable', 'integer', 'exists:provinces,id'],
            'regency_id'       => ['nullable', 'integer', 'exists:regencies,id'],
            'nib'              => $isBranch
                ? ['nullable'] // tak pernah sampai sini — sudah di-422 di atas
                : ['nullable', 'string', 'regex:/^\d{13}$/', Rule::unique($entity->getTable(), 'nib')->ignore($entity->id)],
            // NPWP: siapa pun (HO/cabang) boleh set — normalisasi ke vat global.
            'npwp'             => ['nullable', 'string', 'max:32'],
            'vat_name'         => ['nullable', 'string', 'max:190'],
            'vat_address'      => ['nullable', 'string'],
            'vat_province_id'  => ['nullable', 'integer', 'exists:provinces,id'],
            'vat_regency_id'   => ['nullable', 'integer', 'exists:regencies,id'],
            'vat_postal_code'  => ['nullable', 'string', 'max:10'],
        ]);

        // NPWP: findOrCreate global (1 NPWP = 1 vat). Data vat (nama/alamat)
        // hanya boleh diubah owner vat / admin platform — yang lain 403, klaim dulu.
        if (array_key_exists('npwp', $validated)) {
            $npwp = trim((string) ($validated['npwp'] ?? ''));
            if ($npwp !== '') {
                $vats = app(VatService::class);
                $vatExtra = array_filter([
                    'address'     => $validated['vat_address'] ?? null,
                    'province_id' => $validated['vat_province_id'] ?? null,
                    'regency_id'  => $validated['vat_regency_id'] ?? null,
                    'postal_code' => $validated['vat_postal_code'] ?? null,
                ], fn ($v) => $v !== null);
                $hasVatData = array_key_exists('vat_name', $validated) || $vatExtra !== [];

                $existing = $vats->findByNpwp($npwp);
                if ($existing) {
                    if ($hasVatData && ! $this->canManageVat($user, $existing)) {
                        return response()->json(['message' => 'Data NPWP dikelola pemilik lain. Klaim dulu untuk mengedit.'], 403);
                    }
                    if ($hasVatData && $this->canManageVat($user, $existing)) {
                        $existing->update(array_merge(
                            $vatExtra,
                            array_key_exists('vat_name', $validated) ? ['name' => $validated['vat_name']] : [],
                        ));
                    }
                    $entity->vat_id = $existing->id;
                } else {
                    $vat = $vats->findOrCreate($npwp, $validated['vat_name'] ?? $entity->name, $user->id, $vatExtra);
                    $entity->vat_id = $vat->id;
                }
            }
            // npwp kosong = jangan sentuh vat_id (hindari hapus tak sengaja).
        }

        unset(
            $validated['npwp'],
            $validated['vat_name'],
            $validated['vat_address'],
            $validated['vat_province_id'],
            $validated['vat_regency_id'],
            $validated['vat_postal_code'],
        );

        $entity->update($validated);

        return response()->json($this->companyPayload(
            $entity instanceof Customer ? 'customer' : 'surveyor',
            $entity
        ));
    }

    /**
     * Klaim kepemilikan data vat (owner_id = user). Self-service: entity
     * user harus memakai vat tsb. Dipakai saat HO datang belakangan dan mau
     * mengelola NPWP yang pertama kali diisi cabang.
     */
    public function claimNpwp(Request $request): JsonResponse
    {
        $user = $request->user();

        $entity = Customer::where('admin_id', $user->id)->first()
            ?? Surveyor::where('admin_id', $user->id)->first();

        if (! $entity || ! $entity->vat_id) {
            return response()->json(['message' => 'Entity tidak memiliki NPWP untuk diklaim.'], 422);
        }

        $vat = Vat::find($entity->vat_id);

        if (! $vat) {
            return response()->json(['message' => 'NPWP tidak ditemukan.'], 404);
        }

        $vat->owner_id = $user->id;
        $vat->save();

        return response()->json([
            'message' => 'Anda kini pengelola data NPWP.',
            'data'    => ['owner_id' => $user->id],
        ]);
    }

    /**
     * Upload file NPWP (1 versi: replace). Wajib owner vat / admin platform.
     * File disimpan disk local (storage/app/npwp — private), bukan public.
     */
    public function uploadNpwpFile(Request $request): JsonResponse
    {
        $user = $request->user();

        $entity = Customer::where('admin_id', $user->id)->first()
            ?? Surveyor::where('admin_id', $user->id)->first();

        if (! $entity || ! $entity->vat_id) {
            return response()->json(['message' => 'Simpan NPWP dulu sebelum upload file.'], 422);
        }

        $vat = Vat::find($entity->vat_id);

        if (! $this->canManageVat($user, $vat)) {
            return response()->json(['message' => 'Data NPWP dikelola pemilik lain. Klaim dulu.'], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $file = $validated['file'];
        $path = $file->store('npwp', 'local');

        // Replace 1 versi: hapus attachment + file fisik lama.
        $old = Attachment::where('rel_type', 'vat')->where('rel_id', $vat->id)->get();
        foreach ($old as $o) {
            Storage::disk($o->disk)->delete($o->path);
        }
        Attachment::where('rel_type', 'vat')->where('rel_id', $vat->id)->delete();

        Attachment::create([
            'rel_type'      => 'vat',
            'rel_id'        => $vat->id,
            'disk'          => 'local',
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'extension'     => $file->getClientOriginalExtension(),
        ]);

        return response()->json(['message' => 'File NPWP tersimpan.']);
    }

    /**
     * Unduh file NPWP (auth; semua entity yang memakai vat boleh lihat).
     */
    public function downloadNpwpFile(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $user = $request->user();

        $entity = Customer::where('admin_id', $user->id)->first()
            ?? Surveyor::where('admin_id', $user->id)->first();

        if (! $entity || ! $entity->vat_id) {
            return response()->json(['message' => 'Entity tidak memiliki NPWP.'], 404);
        }

        $att = Attachment::where('rel_type', 'vat')->where('rel_id', $entity->vat_id)->latest('id')->first();

        if (! $att) {
            return response()->json(['message' => 'Belum ada file NPWP.'], 404);
        }

        return Storage::disk($att->disk)->download($att->path, $att->original_name);
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
     * KONSEP (keputusan 2026-09-10): My Company = entity tempat user
     * TERDAFTAR (HO ATAU cabang), bukan HO. User cabang lihat cabangnya
     * sendiri; referensi HO hanya info (parent_company). My Branch = daftar
     * cabang anak, hanya utk HO; user cabang: kosong.
     *
     * @param  'customer'|'surveyor'  $type
     * @param  Customer|Surveyor  $entity  row entity user (HO atau cabang)
     */
    private function companyPayload(string $type, $entity): array
    {
        $model = $type === 'customer' ? Customer::class : Surveyor::class;

        // My Company = entity user sendiri.
        $company = $entity->load(self::ENTITY_WITH);

        // Referensi kantor pusat utk user cabang (read-only info).
        $parentCompany = $entity->parent_id
            ? $model::find($entity->parent_id, ['id', 'code', 'name'])
            : null;

        // My Branch: HO -> semua cabang anak; user cabang -> kosong.
        $branches = $entity->type === 'branch'
            ? collect()
            : $model::where('parent_id', $entity->id)->where('type', 'branch')->with(self::ENTITY_WITH)->orderBy('id')->get();

        return [
            'type'           => $type,
            'company'        => $company,
            'entity'         => $entity->only(['id', 'code', 'name', 'type', 'parent_id']),
            'parent_company' => $parentCompany,
            'branches'       => $branches,
        ];
    }
}
