<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * SaaS tenant-isolation suite: a user of tenant A must never read, change,
 * reference or even detect the existence of tenant B's data.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $adminA;

    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->adminA = $this->makeMember(Role::OrgAdmin);
        Project::factory()->forTenant(Tenant::find($this->adminA->tenant_id))->count(2)->create();

        $tenantB = Tenant::factory()->create();
        $this->projectB = Project::factory()->forTenant($tenantB)->create(['name' => 'Secret B project']);
    }

    public function test_project_listing_only_contains_own_tenant(): void
    {
        $this->actingAsMember($this->adminA)->getJson('/api/v1/tracker/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['name' => 'Secret B project']);
    }

    public function test_cannot_read_update_or_delete_another_tenants_project(): void
    {
        $this->actingAsMember($this->adminA);

        $this->getJson("/api/v1/tracker/projects/{$this->projectB->id}")->assertNotFound()->assertJsonPath('message', 'Resource not found.');
        $this->patchJson("/api/v1/tracker/projects/{$this->projectB->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/tracker/projects/{$this->projectB->id}")->assertNotFound();

        $this->assertSame('Secret B project', Project::withoutGlobalScopes()->find($this->projectB->id)->name);
    }

    public function test_cannot_log_work_against_another_tenants_project(): void
    {
        $this->actingAsMember($this->adminA)
            ->postJson("/api/v1/tracker/projects/{$this->projectB->id}/activities", [
                'activity_date' => now()->toDateString(), 'title' => 'Injected', 'hours_spent' => 1, 'progress_percent' => 5,
            ])->assertNotFound();

        $this->assertDatabaseMissing('project_activities', ['title' => 'Injected']);
    }

    public function test_tenant_header_is_ignored_for_regular_users(): void
    {
        $slugB = Tenant::find($this->projectB->tenant_id)->slug;

        $this->actingAsMember($this->adminA)
            ->withHeader('X-Tenant', $slugB)
            ->getJson('/api/v1/tracker/projects')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Secret B project']);
    }

    public function test_new_records_are_stamped_with_the_current_tenant(): void
    {
        $this->actingAsMember($this->adminA)
            ->postJson('/api/v1/tracker/projects', ['name' => 'Stamped', 'start_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString()])
            ->assertCreated();

        $this->assertDatabaseHas('projects', ['name' => 'Stamped', 'tenant_id' => $this->adminA->tenant_id]);
    }

    public function test_super_admin_can_list_all_tenants_but_members_cannot(): void
    {
        $this->actingAsMember($this->adminA)->getJson('/api/v1/admin/tenants')->assertForbidden();

        $super = User::factory()->superAdmin()->create();
        $this->actingAsMember($super)->getJson('/api/v1/admin/tenants')->assertOk()->assertJsonCount(2, 'data');
    }
}
