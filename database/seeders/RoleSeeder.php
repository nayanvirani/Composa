<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdminRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Super admin roles and permissions. The first Owner comes from ADMIN_OWNER_EMAIL (and optional
 * ADMIN_OWNER_PASSWORD; leave it empty to sign in with Google only).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(AdminRoles::PERMISSIONS) as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (AdminRoles::roles() as $role => $permissions) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web'])->syncPermissions($permissions);
        }

        if ($email = env('ADMIN_OWNER_EMAIL')) {
            $owner = User::query()->firstOrCreate(['email' => strtolower($email)], [
                'name' => env('ADMIN_OWNER_NAME', Str::before($email, '@')),
                'password' => env('ADMIN_OWNER_PASSWORD') ?: null,
                'active' => true,
            ]);
            $owner->assignRole('Owner');
        }
    }
}
