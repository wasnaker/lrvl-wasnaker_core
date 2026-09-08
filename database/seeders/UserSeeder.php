<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles if they don't exist
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'sanctum']);

        // Create admin account
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('adminpass'),
                'is_active' => true,
            ]
        );
        $admin->assignRole($adminRole);

        // Create demo user
        $demo = User::firstOrCreate(
            ['email' => 'demo@spine.lan'],
            [
                'name' => 'Demo User',
                'email' => 'demo@spine.lan',
                'password' => Hash::make('demo123'),
                'is_active' => true,
            ]
        );
        $demo->assignRole($userRole);
    }
}