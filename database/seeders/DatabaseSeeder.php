<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Safe to run more than once. Model events stay on,
     * so seeded records get their creators and audit trail like any other.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        // The two demo profiles from PLAN.md: a construction site and a manufacturing plant.
        $sites = [
            'KL-TWR' => ['Menara Damai tower project', 'Jalan Ampang, Kuala Lumpur', ['Basement', 'Block A', 'Crane zone', 'Site office']],
            'SHA-PLT' => ['Shah Alam stamping plant', 'Seksyen 15, Shah Alam', ['Press line', 'Warehouse', 'Boiler house', 'Loading bay']],
        ];

        foreach ($sites as $code => [$name, $address, $areas]) {
            $site = Site::query()->firstOrCreate(['code' => $code], ['name' => $name, 'address' => $address]);

            foreach ($areas as $area) {
                $site->areas()->firstOrCreate(['name' => $area]);
            }
        }

        $contractor = Contractor::query()->firstOrCreate(['name' => 'Bina Scaffold Sdn Bhd'], [
            'registration_no' => '201901012345', 'contact_name' => 'Ahmad Razak', 'email' => 'safety@binascaffold.example',
            'phone' => '+60 3-1234 5678', 'insurance_expires_on' => now()->addMonths(8)->toDateString(), 'approved' => true,
        ]);

        // One demo account per role: <role>@example.com / Zx123456. Site staff work at the tower project.
        $password = Hash::make('Zx123456');
        $tower = Site::query()->where('code', 'KL-TWR')->value('id');

        foreach (array_keys(RolesSeeder::ROLES) as $role) {
            User::query()->firstOrCreate(['email' => "{$role}@example.com"], [
                'name' => Str::headline($role), 'password' => $password, 'email_verified_at' => now(),
                'site_id' => in_array($role, ['supervisor', 'permit-issuer', 'worker'], true) ? $tower : null,
                'contractor_id' => $role === 'worker' ? $contractor->id : null,
            ])->syncRoles([$role]);
        }
    }
}
