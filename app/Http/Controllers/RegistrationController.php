<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Agency\Models\Agency;
use Modules\Association\Models\Association;
use Modules\Customer\Models\Customer;
use Modules\Surveyor\Models\Surveyor;

/**
 * Registrasi publik (self-registration) — endpoint TIDAK butuh auth.
 *
 * Alur verify-then-create (2026-09-10): user/entity BELUM dibuat saat
 * submit. System kirim kode 6 digit via email, pending disimpan di redis
 * (TTL 15 menit). Kode benar → user + entity dibuat LANGSUNG AKTIF
 * (email_verified_at=now, is_active=true, role 'user'). Tidak ada aktivasi
 * admin. Parameter: kode 6 digit, TTL 15 mnt, max 5 percobaan, resend
 * cooldown 60 dtk, rate limit daftar 3x/jam per IP+email.
 *
 * Endpoint:
 *   POST /api/v1/register        → simpan pending + kirim kode
 *   POST /api/v1/register/verify → cek kode, create user+entity
 *   POST /api/v1/register/resend → kirim ulang kode (cooldown 60 dtk)
 */
class RegistrationController extends Controller
{
    private const PENDING_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    private function pendingKey(string $email): string
    {
        return 'reg:pending:' . strtolower($email);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username'           => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/', 'unique:users,username'],
            'email'              => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password'           => ['required', 'string', 'min:8'],
            'nama'               => ['required', 'string', 'max:190'],
            'tujuan'             => ['required', Rule::in(['customer', 'surveyor', 'association', 'agency'])],
            // customer: pilih kantor pusat atau cabang; cabang boleh tanpa HO (cabang-register-duluan).
            'customer_type'      => ['required_if:tujuan,customer', 'nullable', Rule::in(['pusat', 'cabang'])],
            'parent_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            // agency: pilih induk atau unit; unit wajib punya induk.
            'agency_type'        => ['required_if:tujuan,agency', 'nullable', Rule::in(['induk', 'unit'])],
            'parent_agency_id'   => ['required_if:agency_type,unit', 'nullable', 'integer', 'exists:agencies,id'],
        ]);

        if (($validated['customer_type'] ?? null) === 'cabang' && ! empty($validated['parent_customer_id'])) {
            $parent = Customer::find($validated['parent_customer_id']);
            if (! $parent || $parent->type !== 'customer') {
                return response()->json(['message' => 'Kantor pusat tidak valid.'], 422);
            }
        }

        if (($validated['agency_type'] ?? null) === 'unit') {
            $parent = Agency::find($validated['parent_agency_id']);
            if (! $parent || $parent->type !== 'agency') {
                return response()->json(['message' => 'Induk agency tidak valid.'], 422);
            }
        }

        if (($validated['agency_type'] ?? null) === 'induk' && ! empty($validated['parent_agency_id'])) {
            return response()->json(['message' => 'Agency induk tidak boleh memiliki induk.'], 422);
        }

        // Anti email-bombing: 3x/jam per IP+email.
        $rlKey = 'reg:rl:' . ($request->ip() ?? 'unknown') . ':' . strtolower($validated['email']);
        if (RateLimiter::tooManyAttempts($rlKey, 3)) {
            return response()->json(['message' => 'Terlalu banyak permintaan. Coba lagi nanti.'], 429);
        }
        RateLimiter::hit($rlKey, 3600);

        $email = strtolower($validated['email']);
        $code = (string) random_int(100000, 999999);

        // Password di-hash SEBELUM masuk cache; kode disimpan hash.
        $payload = $validated;
        $payload['password'] = Hash::make($validated['password']);

        Cache::put($this->pendingKey($email), [
            'code'     => Hash::make($code),
            'payload'  => $payload,
            'attempts' => 0,
        ], now()->addMinutes(self::PENDING_TTL_MINUTES));

        try {
            Mail::to($email)->send(new RegistrationCode($code));
        } catch (\Throwable $e) {
            Cache::forget($this->pendingKey($email));
            Log::error('[Register] gagal kirim kode', ['email' => $email, 'err' => $e->getMessage()]);

            return response()->json(['message' => 'Gagal mengirim email. Coba lagi.'], 500);
        }

        return response()->json([
            'message' => 'Kode verifikasi dikirim ke email ' . $email . '.',
            'data'    => ['email' => $email],
        ], 201);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'code'  => ['required', 'string', 'digits:6'],
        ]);

        $email = strtolower($validated['email']);
        $pending = Cache::get($this->pendingKey($email));

        if (! $pending) {
            return response()->json(['message' => 'Kode tidak ditemukan atau kedaluwarsa. Silakan daftar ulang.'], 422);
        }

        $attempts = $pending['attempts'] + 1;
        if ($attempts > self::MAX_ATTEMPTS) {
            Cache::forget($this->pendingKey($email));

            return response()->json(['message' => 'Terlalu banyak percobaan. Silakan daftar ulang.'], 422);
        }

        if (! Hash::check($validated['code'], $pending['code'])) {
            $pending['attempts'] = $attempts;
            Cache::put($this->pendingKey($email), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

            return response()->json(['message' => 'Kode salah.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($pending) {
                return $this->createAccount($pending['payload']);
            });
        } catch (\Throwable $e) {
            Log::error('[Register] gagal create akun', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Pendaftaran gagal: ' . $e->getMessage()], 500);
        }

        Cache::forget($this->pendingKey($email));

        return response()->json([
            'message' => 'Registrasi berhasil. Akun aktif, silakan masuk.',
            'data'    => [
                'user_id'   => $result['user']->id,
                'entity_id' => $result['entity']->id,
                'entity'    => $result['entity']->only(['type', 'code', 'name', 'is_active']),
            ],
        ], 201);
    }

    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
        ]);

        $email = strtolower($validated['email']);
        $pending = Cache::get($this->pendingKey($email));

        if (! $pending) {
            return response()->json(['message' => 'Pendaftaran tidak ditemukan. Silakan daftar ulang.'], 422);
        }

        $rlKey = 'reg:resend:' . $email;
        if (RateLimiter::tooManyAttempts($rlKey, 1)) {
            return response()->json(['message' => 'Tunggu 60 detik sebelum kirim ulang.'], 429);
        }
        RateLimiter::hit($rlKey, self::RESEND_COOLDOWN_SECONDS);

        $code = (string) random_int(100000, 999999);
        $pending['code'] = Hash::make($code);
        $pending['attempts'] = 0;
        Cache::put($this->pendingKey($email), $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));

        try {
            Mail::to($email)->send(new RegistrationCode($code));
        } catch (\Throwable $e) {
            Log::error('[Register] gagal kirim ulang kode', ['email' => $email, 'err' => $e->getMessage()]);

            return response()->json(['message' => 'Gagal mengirim email. Coba lagi.'], 500);
        }

        return response()->json(['message' => 'Kode baru terkirim ke email ' . $email . '.']);
    }

    /**
     * Buat user + entity (transaksi) dari payload registrasi yang sudah
     * tervalidasi. Dipanggil hanya setelah kode verifikasi benar.
     */
    private function createAccount(array $payload): array
    {
        $code = 'REG-' . strtoupper(substr((string) Str::ulid(), 0, 10));

        $user = User::create([
            'name'      => $payload['username'],
            'username'  => $payload['username'],
            'email'     => $payload['email'],
            'password'  => $payload['password'], // sudah hash — cast 'hashed' deteksi $2y$ dan tidak hash ulang
            'is_active' => true,
        ]);
        $user->email_verified_at = now(); // bukan fillable → set langsung
        $user->save();
        $user->assignRole('user'); // role dasar (permission isi profile)

        // Role admin entity sesuai registrasi (TAMBAH, bukan sync).
        $adminRole = match ($payload['tujuan']) {
            'customer'    => ($payload['customer_type'] ?? 'pusat') === 'cabang' ? 'customer-branch-admin' : 'customer-admin',
            'surveyor'    => 'surveyor-admin', // registrasi = kantor pusat
            'association' => 'association-admin',
            'agency'      => ($payload['agency_type'] ?? 'induk') === 'unit' ? 'agency-unit-admin' : 'agency',
        };
        $user->assignRole($adminRole);

        $entity = match ($payload['tujuan']) {
            'customer' => Customer::create([
                'type'      => ($payload['customer_type'] ?? 'pusat') === 'cabang' ? 'branch' : 'customer',
                'code'      => $code,
                'name'      => $payload['nama'],
                'parent_id' => $payload['parent_customer_id'] ?? null,
                'admin_id'  => $user->id,
                'is_active' => false, // entity NON-AKTIF — diaktifkan admin
            ]),
            'surveyor' => Surveyor::create([
                'type'      => 'surveyor', // default kantor pusat; cabang dibuat belakangan oleh HO/admin
                'code'      => $code,
                'name'      => $payload['nama'],
                'admin_id'  => $user->id,
                'is_active' => false,
            ]),
            'association' => Association::create([
                'code'      => $code,
                'name'      => $payload['nama'],
                'admin_id'  => $user->id,
                'is_active' => false,
            ]),
            'agency' => Agency::create([
                'type'      => ($payload['agency_type'] ?? 'induk') === 'unit' ? 'unit' : 'agency',
                'code'      => $code,
                'name'      => $payload['nama'],
                'parent_id' => $payload['parent_agency_id'] ?? null,
                'admin_id'  => $user->id,
                'is_active' => false,
            ]),
        };

        return ['user' => $user, 'entity' => $entity];
    }
}
