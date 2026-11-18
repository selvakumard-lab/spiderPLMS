<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter', 'name' => 'Starter', 'sort_order' => 1,
                'description' => 'For small teams getting their delivery process in order.',
                'price_monthly' => 1900, 'price_yearly' => 19000,
                'max_users' => 10, 'max_projects' => 10, 'storage_gb' => 10,
                'features' => ['Project progress tracking', 'Activity, meeting & change logs', 'Client dependency tracking', 'AI delay estimates', 'Email support'],
            ],
            [
                'code' => 'growth', 'name' => 'Growth', 'sort_order' => 2,
                'description' => 'For growing agencies and product teams.',
                'price_monthly' => 4900, 'price_yearly' => 49000,
                'max_users' => 50, 'max_projects' => null, 'storage_gb' => 100,
                'features' => ['Everything in Starter', 'Unlimited projects', 'PDF & Excel reports', 'Priority support'],
            ],
            [
                'code' => 'enterprise', 'name' => 'Enterprise', 'sort_order' => 3,
                'description' => 'For organisations that need control, scale and compliance.',
                'price_monthly' => 9900, 'price_yearly' => 99000,
                'max_users' => null, 'max_projects' => null, 'storage_gb' => 1000,
                'features' => ['Everything in Growth', 'Unlimited users', 'Custom domain & SSO', 'Audit log export', 'Dedicated success manager', '99.9% uptime SLA'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
