<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectChatMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ProjectThreadTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Client $client;

    private Project $project;

    private User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);

        app(TenantContext::class)->runAs($this->tenant, function (): void {
            $this->client = Client::create(['name' => 'Acme Ltd', 'email' => 'casey@acme.test']);
            $this->project = Project::create(['name' => 'Acme Website', 'client_id' => $this->client->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(20), 'status_updated_at' => now()]);
        });

        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk();
        $this->portalUser = User::withoutGlobalScopes()->where('client_id', $this->client->id)->firstOrFail();
    }

    public function test_the_client_sees_team_replies_and_the_team_sees_client_messages(): void
    {
        $this->actingAsMember($this->portalUser)->postJson("/api/v1/portal/projects/{$this->project->id}/chat", ['body' => 'When is the logo review?'])
            ->assertCreated()->assertJsonPath('data.sender', 'client')->assertJsonPath('data.body', 'When is the logo review?');

        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/thread", ['body' => 'Thursday at 11:00.'])
            ->assertCreated()->assertJsonPath('data.sender', 'team');

        $this->actingAsMember($this->portalUser)->getJson("/api/v1/portal/projects/{$this->project->id}/chat")
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sender', 'client')
            ->assertJsonPath('data.1.sender', 'team')
            ->assertJsonPath('data.1.body', 'Thursday at 11:00.');

        $this->actingAsMember($this->admin)->getJson("/api/v1/tracker/projects/{$this->project->id}/thread")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_each_side_is_notified_of_the_others_messages_but_not_their_own(): void
    {
        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/thread", ['body' => 'We need your brand files.'])->assertCreated();

        $this->assertSame(1, $this->portalUser->notifications()->get()->where('data.kind', 'message')->count());
        $this->assertSame(0, $this->admin->notifications()->get()->count());

        $this->actingAsMember($this->portalUser)->postJson("/api/v1/portal/projects/{$this->project->id}/chat", ['body' => 'Sending today.'])->assertCreated();

        $this->assertSame(1, $this->admin->notifications()->get()->where('data.kind', 'message')->count());
        $this->assertSame(1, $this->portalUser->notifications()->get()->count());
    }

    public function test_a_client_cannot_read_or_write_another_clients_thread(): void
    {
        $other = app(TenantContext::class)->runAs($this->tenant, function () {
            $rival = Client::create(['name' => 'Rival Inc', 'email' => 'r@rival.test']);

            return Project::create(['name' => 'Rival Project', 'client_id' => $rival->id, 'start_date' => now(), 'due_date' => now()->addDays(5)]);
        });
        ProjectChatMessage::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $other->id, 'body' => 'Private to the rival']);

        $this->actingAsMember($this->portalUser)->getJson("/api/v1/portal/projects/{$other->id}/chat")->assertNotFound();
        $this->postJson("/api/v1/portal/projects/{$other->id}/chat", ['body' => 'Hello'])->assertNotFound();
    }

    public function test_portal_accounts_cannot_use_the_team_thread_and_staff_cannot_use_the_portal_one(): void
    {
        $this->actingAsMember($this->portalUser)->getJson("/api/v1/tracker/projects/{$this->project->id}/thread")->assertForbidden();
        $this->actingAsMember($this->admin)->getJson("/api/v1/portal/projects/{$this->project->id}/chat")->assertForbidden();
    }

    public function test_messages_are_validated_and_the_team_needs_a_client_on_the_project(): void
    {
        $this->actingAsMember($this->portalUser)->postJson("/api/v1/portal/projects/{$this->project->id}/chat", ['body' => ''])->assertJsonValidationErrors('body');
        $this->postJson("/api/v1/portal/projects/{$this->project->id}/chat", ['body' => str_repeat('a', 2001)])->assertJsonValidationErrors('body');

        $solo = app(TenantContext::class)->runAs($this->tenant, fn () => Project::create(['name' => 'No Client', 'start_date' => now(), 'due_date' => now()->addDays(5)]));
        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$solo->id}/thread", ['body' => 'Hi'])->assertUnprocessable();
    }
}
