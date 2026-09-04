<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $teamResolver = app(PermissionRegistrar::class);

        $teamResolver->setPermissionsTeamId(DefaultRoles::PLATFORM_TEAM_ID);

        $superAdminRole = Role::firstOrCreate([
            'name' => DefaultRoles::PLATFORM_SUPER_ADMIN,
            'guard_name' => 'web',
            'tenant_id' => DefaultRoles::PLATFORM_TEAM_ID,
        ]);

        // Real inboxes (not a .test domain) so outbound email — SendGrid
        // notifications included — actually has somewhere to land.
        $platformSuperUsers = [
            ['name' => 'Dingulwazi Zondo', 'email' => 'dingulwazi.zondo@innovativestart.co.uk'],
            ['name' => 'Trevor Ndlovu', 'email' => 'trevor.ndlovu@innovativestart.co.uk'],
        ];
        foreach ($platformSuperUsers as $superUser) {
            // User::create(), not the factory — keeps this seeder runnable
            // against a --no-dev (Faker-less) production install.
            $user = User::create([
                'tenant_id' => null,
                'name' => $superUser['name'],
                'email' => $superUser['email'],
                'email_verified_at' => now(),
                'password' => 'password',
            ]);
            $user->assignRole($superAdminRole);
        }

        $tenant = Tenant::create([
            'name' => 'Demo Care Group',
            'slug' => 'demo-care-group',
            'country' => 'Zimbabwe',
            'timezone' => 'Africa/Harare',
            'currency' => 'USD',
            'locale' => 'en',
            'plan' => 'professional',
            'status' => 'active',
        ]);

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Harare Branch',
            'country' => 'Zimbabwe',
            'region' => 'Harare',
        ]);

        $teamResolver->setPermissionsTeamId($tenant->id);

        $orgAdminRole = Role::where('name', 'Organization Admin')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $tenantAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Org Admin',
            'email' => 'orgadmin@demo-care-group.test',
            'password' => 'password',
        ]);
        $tenantAdmin->assignRole($orgAdminRole);

        $teamResolver->setPermissionsTeamId(DefaultRoles::PLATFORM_TEAM_ID);

        $this->call(DemoDataSeeder::class);

        // Additional lightweight organizations (no full demo dataset — just
        // enough of a shell to make the platform-admin Organizations list
        // genuinely demoable with more than one row and a mix of statuses).
        $this->seedAdditionalOrganizations($teamResolver);
    }

    private function seedAdditionalOrganizations(PermissionRegistrar $teamResolver): void
    {
        $organizations = [
            [
                'name' => 'Sunrise Homecare',
                'slug' => 'sunrise-homecare',
                'country' => 'South Africa',
                'timezone' => 'Africa/Johannesburg',
                'currency' => 'ZAR',
                'plan' => 'starter',
                'status' => 'trial',
                'branch' => 'Johannesburg Branch',
                'region' => 'Gauteng',
            ],
            [
                'name' => 'Bulawayo Elder Care',
                'slug' => 'bulawayo-elder-care',
                'country' => 'Zimbabwe',
                'timezone' => 'Africa/Harare',
                'currency' => 'USD',
                'plan' => 'professional',
                'status' => 'suspended',
                'branch' => 'Bulawayo Branch',
                'region' => 'Bulawayo',
            ],
            [
                'name' => 'Green Valley Care Services',
                'slug' => 'green-valley-care',
                'country' => 'Zambia',
                'timezone' => 'Africa/Lusaka',
                'currency' => 'ZMW',
                'plan' => 'enterprise',
                'status' => 'active',
                'branch' => 'Lusaka Branch',
                'region' => 'Lusaka',
            ],
        ];

        foreach ($organizations as $org) {
            $tenant = Tenant::create([
                'name' => $org['name'],
                'slug' => $org['slug'],
                'country' => $org['country'],
                'timezone' => $org['timezone'],
                'currency' => $org['currency'],
                'locale' => 'en',
                'plan' => $org['plan'],
                'status' => $org['status'],
            ]);

            Branch::create([
                'tenant_id' => $tenant->id,
                'name' => $org['branch'],
                'country' => $org['country'],
                'region' => $org['region'],
            ]);

            $teamResolver->setPermissionsTeamId($tenant->id);

            $orgAdminRole = Role::where('name', 'Organization Admin')
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $admin = User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $org['name'] . ' Admin',
                'email' => 'admin@' . $org['slug'] . '.test',
                'password' => 'password',
            ]);
            $admin->assignRole($orgAdminRole);
        }

        $teamResolver->setPermissionsTeamId(DefaultRoles::PLATFORM_TEAM_ID);
    }
}
