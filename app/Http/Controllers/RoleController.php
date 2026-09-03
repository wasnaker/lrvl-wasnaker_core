<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Manajemen role + permission — domain aplikasi.
 *
 * Endpoint:
 *   GET    /api/v1/roles          -> {data:[role+permissions]}
 *   POST   /api/v1/roles
 *   GET    /api/v1/roles/{id}
 *   PUT    /api/v1/roles/{id}
 *   DELETE /api/v1/roles/{id}
 *   GET    /api/v1/permissions    -> {data:[...nama permission]} utk matriks UI
 *
 * Permission = string "feature:capability" (mis. users:create). Role
 * super-admin (config spine.auth.super_admin_role, default "admin") dilindungi:
 * tidak bisa dihapus atau permission-nya diganti — akses penuh via Gate::before.
 */
class RoleController extends Controller
{
    private function superAdminRole(): string
    {
        return (string) config('spine.auth.super_admin_role', 'admin');
    }

    public function index(): JsonResponse
    {
        $data = Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->payload($role));

        return response()->json(['data' => $data]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:190', Rule::unique('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'sanctum']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return response()->json($this->payload($role), 201);
    }

    public function show(int $id): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        return response()->json($this->payload($role));
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        if ($role->name === $this->superAdminRole()) {
            return response()->json(['message' => 'The super-admin role is protected.'], 422);
        }

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:190', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->name = $validated['name'];
        $role->save();

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json($this->payload($role));
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        if ($role->name === $this->superAdminRole()) {
            return response()->json(['message' => 'The super-admin role is protected.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Role is still assigned to users.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }

    /** @return array{id: int, name: string, permissions: list<string>} */
    private function payload(Role $role): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $role->permissions()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
