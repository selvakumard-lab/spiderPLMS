<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ProjectMilestoneTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Client $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);

        app(TenantContext::class)->runAs($this->tenant, function (): void {
            $this->client = Client::create(['name' => 'Acme Ltd', 'email' => 'casey@acme.test']);
            $this->project = Project::create(['name' => 'Acme Website', 'client_id' => $this->client->id, 'start_date' => now()->subDays(10)->toDateString(), 'due_date' => now()->addDays(40)->toDateString(), 'status_updated_at' => now()]);
        });
    }

    private function portalUser(): User
    {
        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk();

        return User::withoutGlobalScopes()->where('client_id', $this->client->id)->firstOrFail();
    }

    private function milestone(array $attributes = []): ProjectMilestone
    {
        return app(TenantContext::class)->runAs($this->tenant, fn () => ProjectMilestone::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id] + $attributes));
    }

    public function test_a_new_project_gets_every_stage_as_a_step_spread_across_its_dates(): void
    {
        $response = $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', [
            'name' => 'Fresh Project', 'start_date' => now()->subDay()->toDateString(), 'due_date' => now()->addDays(60)->toDateString(),
        ])->assertCreated();

        $show = $this->getJson('/api/v1/tracker/projects/'.$response->json('data.id'))->assertOk();
        $steps = $show->json('plan.milestones');

        $this->assertCount(6, $steps);
        $this->assertSame(['Requirements', 'Design', 'Development', 'Testing', 'Deployment', 'Handover'], array_column($steps, 'title'));
        $this->assertSame('in_progress', $steps[0]['status']);
        $this->assertSame('pending', $steps[5]['status']);
        $this->assertSame(range(1, 6), array_column($steps, 'number'));

        foreach ($steps as $step) {
            $this->assertNotNull($step['planned_start']);
            $this->assertLessThanOrEqual(now()->addDays(60)->toDateString(), $step['due_date']);
            $this->assertGreaterThanOrEqual($step['planned_start'], $step['due_date']);
        }
        $this->assertGreaterThan($steps[0]['due_date'], $steps[1]['due_date']);
        $show->assertJsonPath('plan.summary.total', 6)->assertJsonPath('plan.summary.completed', 0)->assertJsonPath('plan.summary.next.title', 'Requirements');
    }

    public function test_an_existing_project_can_generate_its_plan_once(): void
    {
        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/milestones/generate")
            ->assertCreated()->assertJsonCount(6, 'milestones');

        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/milestones/generate")->assertUnprocessable();
    }

    public function test_starting_and_finishing_a_step_stamps_dates_tells_the_client_and_moves_the_stage(): void
    {
        $portal = $this->portalUser();
        $step = $this->milestone(['stage' => 'design', 'title' => 'Design', 'planned_start' => now()->subDay()->toDateString(), 'due_date' => now()->addDays(5)->toDateString()]);

        $this->actingAsMember($this->admin)->patchJson("/api/v1/tracker/milestones/{$step->id}", ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('milestones.0.status', 'in_progress')->assertJsonPath('milestones.0.started_on', now()->toDateString());

        $this->assertSame('design', $this->project->fresh()->stage);

        $this->patchJson("/api/v1/tracker/milestones/{$step->id}", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('milestones.0.display_status', 'completed')->assertJsonPath('milestones.0.timing', 'completed_on_time')
            ->assertJsonPath('milestones.0.completed_on', now()->toDateString())->assertJsonPath('milestones.0.days_late', 0);

        $titles = $portal->notifications()->get()->pluck('data.title')->all();
        $this->assertContains('Step started: Design', $titles);
        $this->assertContains('Step completed: Design', $titles);
    }

    public function test_a_step_past_its_deadline_is_delayed_and_one_finished_after_it_is_completed_late(): void
    {
        $overdue = $this->milestone(['title' => 'Overdue step', 'status' => 'in_progress', 'started_on' => now()->subDays(20)->toDateString(), 'planned_start' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(10)->toDateString()]);
        $late = $this->milestone(['title' => 'Late step', 'sort_order' => 1, 'status' => 'completed', 'started_on' => now()->subDays(20)->toDateString(), 'planned_start' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(10)->toDateString(), 'completed_on' => now()->subDays(5)->toDateString()]);
        $this->milestone(['title' => 'Future step', 'sort_order' => 2, 'planned_start' => now()->addDays(2)->toDateString(), 'due_date' => now()->addDays(9)->toDateString()]);

        $json = $this->actingAsMember($this->admin)->getJson("/api/v1/tracker/projects/{$this->project->id}")->assertOk();

        $json->assertJsonPath('plan.milestones.0.display_status', 'delayed')->assertJsonPath('plan.milestones.0.timing', 'overdue')
            ->assertJsonPath('plan.milestones.1.display_status', 'completed')->assertJsonPath('plan.milestones.1.timing', 'completed_late')
            ->assertJsonPath('plan.milestones.2.display_status', 'pending')->assertJsonPath('plan.milestones.2.timing', 'upcoming')
            ->assertJsonPath('plan.summary.overdue', 1)->assertJsonPath('plan.summary.delayed', 2)->assertJsonPath('plan.summary.next.id', $overdue->id);

        $this->assertGreaterThanOrEqual(5, $json->json('plan.milestones.0.days_late'));
        $this->assertGreaterThanOrEqual(3, $json->json('plan.milestones.1.days_late'));
        $this->assertGreaterThan(5, $json->json('plan.milestones.1.days_taken'));
        $this->assertNotNull($json->json('plan.milestones.2.days_until_due'));
        $this->assertNotNull($late->id);
    }

    public function test_the_client_sees_the_plan_for_their_own_project_but_cannot_change_it(): void
    {
        $portal = $this->portalUser();
        $step = $this->milestone(['title' => 'Build', 'status' => 'in_progress', 'started_on' => now()->toDateString()]);

        $this->actingAsMember($portal)->getJson("/api/v1/portal/projects/{$this->project->id}")
            ->assertOk()->assertJsonPath('plan.milestones.0.title', 'Build')->assertJsonPath('plan.summary.total', 1);

        $this->patchJson("/api/v1/tracker/milestones/{$step->id}", ['status' => 'completed'])->assertForbidden();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/milestones", ['title' => 'Sneaky'])->assertForbidden();
        $this->assertSame('in_progress', $step->fresh()->status);
    }

    public function test_steps_can_be_added_edited_and_deleted_with_validated_dates(): void
    {
        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/milestones", [
            'title' => 'Client sign-off', 'stage' => 'handover', 'planned_start' => now()->toDateString(), 'due_date' => now()->addDays(4)->toDateString(),
        ])->assertCreated()->assertJsonPath('milestones.0.title', 'Client sign-off');

        $id = ProjectMilestone::withoutGlobalScopes()->where('project_id', $this->project->id)->value('id');

        $this->patchJson("/api/v1/tracker/milestones/{$id}", ['due_date' => now()->subDays(3)->toDateString()])->assertJsonValidationErrors('due_date');
        $this->patchJson("/api/v1/tracker/milestones/{$id}", ['status' => 'maybe'])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/milestones", [])->assertJsonValidationErrors('title');

        $this->patchJson("/api/v1/tracker/milestones/{$id}", ['due_date' => now()->addDays(8)->toDateString()])->assertOk();
        $this->deleteJson("/api/v1/tracker/milestones/{$id}")->assertOk()->assertJsonCount(0, 'milestones');
    }

    public function test_client_messages_and_team_replies_land_on_the_shared_timeline(): void
    {
        $portal = $this->portalUser();

        $this->actingAsMember($portal)->postJson("/api/v1/portal/projects/{$this->project->id}/chat", ['body' => 'Can we add a blog page?'])->assertCreated();
        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/thread", ['body' => 'Yes, raise it as a requirement.'])->assertCreated();

        $events = $this->getJson("/api/v1/tracker/projects/{$this->project->id}")->json('events');
        $messages = array_values(array_filter($events, fn ($e) => $e['kind'] === 'message'));

        $this->assertCount(2, $messages);
        $this->assertEqualsCanonicalizing(['client', 'team'], array_column($messages, 'actor'));
        $this->assertNotNull($messages[0]['created_at']);
    }
}
