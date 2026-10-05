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
        'observations' => ['manage', 'create', 'edit', 'delete'],
        'incidents' => ['manage', 'create', 'edit', 'delete'],
        'risk-assessments' => ['manage', 'create', 'edit', 'approve', 'delete'],
        'checklists' => ['manage', 'create', 'edit', 'delete'],
        'inspections' => ['manage', 'create', 'delete'],
        'permits' => ['manage', 'create', 'approve', 'delete'],
        'competencies' => ['manage', 'edit'],
        'toolbox-talks' => ['manage', 'create', 'delete'],
        'documents' => ['manage', 'create', 'edit', 'approve'],
        'audits' => ['manage', 'create', 'edit'],
        'environment' => ['manage', 'edit'],
        'chemicals' => ['manage', 'create', 'edit', 'delete'],
        // Everyone sees and completes the actions they own; these cover the whole register.
        'actions' => ['manage', 'create', 'verify'],
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
            'manage-observations', 'create-observations', 'edit-observations', 'delete-observations',
            'manage-incidents', 'create-incidents', 'edit-incidents', 'delete-incidents',
            'manage-risk-assessments', 'create-risk-assessments', 'edit-risk-assessments', 'approve-risk-assessments', 'delete-risk-assessments',
            'manage-checklists', 'create-checklists', 'edit-checklists', 'delete-checklists',
            'manage-inspections', 'create-inspections', 'delete-inspections',
            'manage-permits', 'create-permits', 'approve-permits', 'delete-permits',
            'manage-competencies', 'edit-competencies',
            'manage-toolbox-talks', 'create-toolbox-talks', 'delete-toolbox-talks',
            'manage-documents', 'create-documents', 'edit-documents', 'approve-documents',
            'manage-audits', 'create-audits', 'edit-audits',
            'manage-environment', 'edit-environment',
            'manage-chemicals', 'create-chemicals', 'edit-chemicals', 'delete-chemicals',
            'manage-actions', 'create-actions', 'verify-actions',
        ],
        'supervisor' => [
            'manage-sites', 'manage-contractors', 'create-contractors', 'edit-contractors',
            'manage-observations', 'create-observations', 'edit-observations',
            'manage-incidents', 'create-incidents', 'edit-incidents',
            'manage-risk-assessments', 'create-risk-assessments', 'edit-risk-assessments',
            'manage-checklists', 'manage-inspections', 'create-inspections',
            'manage-permits', 'create-permits', 'manage-competencies', 'edit-competencies',
            'manage-toolbox-talks', 'create-toolbox-talks', 'manage-documents', 'manage-audits',
            'manage-environment', 'edit-environment', 'manage-chemicals', 'create-chemicals', 'edit-chemicals',
            'manage-actions', 'create-actions', 'verify-actions',
        ],
        'permit-issuer' => ['manage-sites', 'manage-contractors', 'manage-observations', 'create-observations', 'manage-incidents', 'create-incidents',
            'manage-risk-assessments', 'manage-inspections', 'create-inspections',
            'manage-permits', 'create-permits', 'approve-permits', 'manage-competencies',
            'manage-toolbox-talks', 'create-toolbox-talks', 'manage-documents', 'manage-chemicals', 'manage-actions'],
        // Workers can read the chemical register and its safety data sheets (right to know).
        'worker' => ['create-observations', 'manage-chemicals'],
        'auditor' => ['manage-sites', 'manage-contractors', 'manage-observations', 'manage-incidents', 'manage-risk-assessments', 'manage-checklists', 'manage-inspections',
            'manage-permits', 'manage-competencies', 'manage-toolbox-talks', 'manage-documents',
            'manage-audits', 'create-audits', 'edit-audits', 'manage-environment', 'manage-chemicals', 'manage-actions', 'create-actions'],
    ];

    public static function label(string $role): string
    {
        return array_key_exists($role, self::ROLES) ? str_replace('Hse ', 'HSE ', Str::headline($role)) : $role;
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
