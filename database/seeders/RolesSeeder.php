<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Permissions per module, named "<action>-<module>" (manage-users, create-users, ...).
     * "manage" opens the module; each module adds its own line here.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSIONS = [
        'users' => ['manage', 'create', 'edit', 'delete'],
        'sites' => ['manage', 'create', 'edit', 'delete'],
        'contractors' => ['manage', 'create', 'edit', 'delete', 'approve'],
    ];

    /**
     * Built-in roles and their permissions; "*" grants every permission.
     *
     * @var array<string, list<string>>
     */
    public const ROLES = [
        'admin' => ['*'],
        'hse-manager' => [
            'manage-sites', 'create-sites', 'edit-sites', 'delete-sites',
            'manage-contractors', 'create-contractors', 'edit-contractors', 'delete-contractors', 'approve-contractors',
        ],
        'supervisor' => ['manage-sites', 'manage-contractors', 'create-contractors', 'edit-contractors'],
        'permit-issuer' => ['manage-sites', 'manage-contractors'],
        'worker' => [],
        'auditor' => ['manage-sites', 'manage-contractors'],
    ];

    public static function label(string $role): string
    {
        return array_key_exists($role, self::ROLES) ? Str::headline($role) : $role;
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::PERMISSIONS)
            ->flatMap(fn (array $actions, string $module) => array_map(fn (string $action) => "{$action}-{$module}", $actions))
            ->values();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Model events may be off while seeding, so the cached (empty) permission list must be dropped by hand.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions === ['*'] ? $all : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
