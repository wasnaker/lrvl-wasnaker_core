<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission awal: feature users & roles (manajemen akun).
     * Feature modul bisnis ditambahkan saat modulnya lahir (prefix feature:).
     */
    public function run(): void
    {
        $features = ['users', 'roles', 'settings'];

        foreach ($features as $feature) {
            foreach (['view', 'create', 'edit', 'delete'] as $capability) {
                Permission::findOrCreate("{$feature}:{$capability}", 'sanctum');
            }
        }

        // Bersihkan data guard lama (web) — aplikasi API-only, guard = sanctum.
        // Pivot model_has_* ikut terhapus via cascade FK.
        Role::where('guard_name', '!=', 'sanctum')->delete();
        Permission::where('guard_name', '!=', 'sanctum')->delete();

        $admin = Role::findOrCreate('admin', 'sanctum');
        // Admin = super-admin: selalu sinkron semua permission (idempotent).
        $admin->syncPermissions(Permission::all());

        $adminUser = \App\Models\User::where('email', 'admin@wasnaker.lan')->first();
        $adminUser?->assignRole('admin');
    }
}
