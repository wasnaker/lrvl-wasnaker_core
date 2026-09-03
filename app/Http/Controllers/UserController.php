<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Manajemen akun (staff internal) — domain aplikasi, bukan modul.
 *
 * Endpoint:
 *   GET    /api/v1/users
 *   POST   /api/v1/users
 *   GET    /api/v1/users/{id}
 *   PUT    /api/v1/users/{id}
 *   DELETE /api/v1/users/{id}
 *
 * Password opsional saat update (kosong = tidak diganti). Role diberikan
 * via nama (array), diregistrasi middleware permission:users:* per aksi.
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $data = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->payload($user));

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateData($request, null);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => $validated['password'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->syncRoles($user, $validated['roles'] ?? []);

        return response()->json($this->payload($user), 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($this->payload($user));
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $this->validateData($request, $user);

        $user->name      = $validated['name'];
        $user->email     = $validated['email'];
        $user->is_active = $validated['is_active'] ?? $user->is_active;

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        // Jaga: admin tidak boleh menonaktifkan akun sendiri.
        if ($request->user()->id === $user->id && ! $user->is_active) {
            return response()->json(['message' => 'You cannot disable your own account.'], 422);
        }

        $user->save();

        if (array_key_exists('roles', $validated)) {
            $this->syncRoles($user, $validated['roles']);
        }

        // Nonaktif = token dicabut; login diblokir AuthController (is_active).
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json($this->payload($user));
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    /** @return array{name: string, email: string, password: ?string, is_active: bool, roles?: list<string>} */
    private function validateData(Request $request, ?User $user): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:190'],
            'email'     => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'password'  => [$user ? 'sometimes' : 'required', 'string', 'min:8', 'max:190'],
            'is_active' => ['sometimes', 'boolean'],
            'roles'     => ['sometimes', 'array'],
            'roles.*'   => ['string', 'exists:roles,name'],
        ]) + ($user ? ['password' => null] : []);
    }

    /** @param list<string> $roleNames */
    private function syncRoles(User $user, array $roleNames): void
    {
        $roles = Role::whereIn('name', $roleNames)->get();
        $user->syncRoles($roles);
    }

    /** @return array{id: int, name: string, email: string, is_active: bool, roles: list<string>} */
    private function payload(User $user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'is_active' => $user->is_active,
            'roles'     => $user->getRoleNames()->all(),
        ];
    }
}
