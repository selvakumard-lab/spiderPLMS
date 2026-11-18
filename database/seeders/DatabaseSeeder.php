<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GeoSeeder::class,
            PlanSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $superAdmin = User::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => null, 'email' => 'selvakumar.d@spiderindia.net'],
            ['name' => 'Selvakumar D', 'job_title' => 'Super Admin', 'password' => str()->random(40), 'email_verified_at' => now()],
        );
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        // A full demo workspace for development only; never seeded into a production database.
        if (app()->environment('local')) {
            $this->call(TestDataSeeder::class);
        }
    }
}
