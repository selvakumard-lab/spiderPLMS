<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\GeoSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

trait InteractsWithTenants
{
    protected function seedPlatform(): void
    {
        $this->seed([GeoSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);
    }

    /**
     * Create a user with a role inside a tenant (the tenant is created when omitted).
     */
    protected function makeMember(Role $role, ?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();
        $context = app(TenantContext::class);

        return $context->runAs($tenant, function () use ($tenant, $role): User {
            $user = User::factory()->forTenant($tenant)->create();
            $user->assignRole($role->value);

            return $user;
        });
    }

    /**
     * A project in the user's tenant.
     */
    protected function projectFor(User $user, array $attributes = []): Project
    {
        $tenant = Tenant::find($user->tenant_id);

        return Project::factory()->forTenant($tenant)->create($attributes);
    }

    /**
     * Authenticate as the user for API calls. The tenant context is cleared so the
     * request middleware — not test setup — decides which tenant is bound.
     */
    protected function actingAsMember(User $user): static
    {
        app(TenantContext::class)->set(null);
        Sanctum::actingAs($user);

        return $this;
    }
}
