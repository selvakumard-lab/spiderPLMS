<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ClientUpdateMail;
use App\Mail\LoginCodeMail;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientPortalTest extends TestCase
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
        Storage::fake('local');

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);

        app(TenantContext::class)->runAs($this->tenant, function (): void {
            $this->client = Client::create(['name' => 'Acme Ltd', 'contact_person' => 'Casey Client', 'email' => 'casey@acme.test']);
            $this->project = Project::create([
                'name' => 'Acme Website', 'client_id' => $this->client->id, 'start_date' => now()->subDays(10), 'due_date' => now()->addDays(20), 'status_updated_at' => now(),
            ]);
        });
    }

    private function portalUser(): User
    {
        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk()->assertJsonPath('data.has_portal_access', true);

        return User::withoutGlobalScopes()->where('client_id', $this->client->id)->firstOrFail();
    }

    public function test_staff_can_give_and_remove_portal_access_and_it_needs_an_email(): void
    {
        $noEmail = app(TenantContext::class)->runAs($this->tenant, fn () => Client::create(['name' => 'No Email Co']));
        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$noEmail->id}/portal-access")->assertJsonValidationErrors('email');

        $user = $this->portalUser();
        $this->assertSame('casey@acme.test', $user->email);
        $this->assertTrue($user->isClient());

        $this->actingAsMember($this->admin)->deleteJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk()->assertJsonPath('data.has_portal_access', false);
        $this->assertNull(User::withoutGlobalScopes()->where('client_id', $this->client->id)->withTrashed()->first());
    }

    public function test_a_staff_email_cannot_become_a_portal_login(): void
    {
        $this->client->forceFill(['email' => $this->admin->email])->save();

        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertJsonValidationErrors('email');
    }

    public function test_a_client_signs_in_with_the_workspace_code_and_is_flagged_as_a_client(): void
    {
        $this->portalUser();
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $this->tenant->slug, 'email' => 'casey@acme.test'])->assertOk();
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->hasTo('casey@acme.test');
        });
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $this->tenant->slug, 'email' => 'casey@acme.test', 'code' => $code])
            ->assertOk()->assertJsonPath('user.is_client', true);
    }

    public function test_a_client_sees_only_their_own_projects_and_never_the_team_workspace(): void
    {
        $user = $this->portalUser();
        $other = app(TenantContext::class)->runAs($this->tenant, function () {
            $rival = Client::create(['name' => 'Rival Inc', 'email' => 'r@rival.test']);

            return Project::create(['name' => 'Secret Rival Project', 'client_id' => $rival->id, 'start_date' => now(), 'due_date' => now()->addDays(5)]);
        });

        $this->actingAsMember($user)->getJson('/api/v1/portal/projects')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Acme Website');

        $this->getJson("/api/v1/portal/projects/{$other->id}")->assertNotFound();

        // The team's tracker, client list and settings are closed to portal accounts.
        $this->getJson('/api/v1/tracker/projects')->assertForbidden();
        $this->getJson("/api/v1/tracker/projects/{$this->project->id}")->assertForbidden();
        $this->getJson('/api/v1/clients')->assertForbidden();
        $this->getJson('/api/v1/workspace/settings')->assertForbidden();

        // And team members cannot use the portal.
        $this->actingAsMember($this->admin)->getJson('/api/v1/portal/projects')->assertForbidden();
    }

    public function test_the_client_sees_the_workflow_with_time_tracking_and_documents_but_not_internal_notes(): void
    {
        $user = $this->portalUser();

        $this->actingAsMember($this->admin);
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/activities", [
            'activity_date' => now()->toDateString(), 'title' => 'Design homepage', 'hours_spent' => 6, 'estimated_hours' => 8, 'status' => 'completed', 'progress_percent' => 40,
        ])->assertCreated();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/documents", [
            'title' => 'Homepage mockup', 'note' => 'v1 for review', 'file' => UploadedFile::fake()->create('mockup.pdf', 50, 'application/pdf'),
        ])->assertCreated();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/documents", [
            'title' => 'Internal costings', 'visible_to_client' => false, 'file' => UploadedFile::fake()->create('costs.xlsx', 10),
        ])->assertCreated();

        $response = $this->actingAsMember($user)->getJson("/api/v1/portal/projects/{$this->project->id}")->assertOk();
        $response->assertJsonPath('steps.0.title', 'Design homepage')
            ->assertJsonPath('steps.0.number', 1)
            ->assertJsonPath('steps.0.status', 'completed')
            ->assertJsonPath('steps.0.hours_spent', 6)
            ->assertJsonPath('summary.hours_spent', 6)
            ->assertJsonPath('summary.progress_percent', 40)
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.title', 'Homepage mockup')
            ->assertJsonMissingPath('steps.0.ai_analysis')
            ->assertJsonMissingPath('data.ai_summary');

        $documentId = $response->json('documents.0.id');
        $this->get("/api/v1/portal/documents/{$documentId}/download")->assertOk();

        $hidden = ProjectDocument::withoutGlobalScopes()->where('title', 'Internal costings')->firstOrFail();
        $this->getJson("/api/v1/portal/documents/{$hidden->id}/download")->assertNotFound();
    }

    public function test_updates_become_web_notifications_that_can_be_read(): void
    {
        $user = $this->portalUser();

        $this->actingAsMember($this->admin);
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/activities", ['activity_date' => now()->toDateString(), 'title' => 'Build login', 'status' => 'in_progress', 'progress_percent' => 20])->assertCreated();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/documents", ['title' => 'Spec', 'file' => UploadedFile::fake()->create('spec.pdf', 5)])->assertCreated();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/client-dependencies", ['title' => 'Brand logo', 'requested_on' => now()->toDateString()])->assertCreated();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/documents", ['title' => 'Private', 'visible_to_client' => false, 'file' => UploadedFile::fake()->create('p.pdf', 5)])->assertCreated();

        $list = $this->actingAsMember($user)->getJson('/api/v1/notifications')->assertOk();
        $titles = collect($list->json('data'))->pluck('title');

        $total = count($titles);
        $this->assertGreaterThanOrEqual(3, $total);
        $this->assertSame($total, $list->json('unread'));
        $this->assertTrue($titles->contains('New step: Build login'));
        $this->assertTrue($titles->contains('New document: Spec'));
        $this->assertTrue($titles->contains('We need something from you: Brand logo'));
        $this->assertFalse($titles->contains('New document: Private'));

        $first = $list->json('data.0.id');
        $this->postJson("/api/v1/notifications/{$first}/read")->assertOk()->assertJsonPath('unread', $total - 1);
        $this->postJson('/api/v1/notifications/read')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_deleting_a_client_removes_their_portal_login(): void
    {
        $user = $this->portalUser();

        $this->actingAsMember($this->admin)->deleteJson("/api/v1/clients/{$this->client->id}")->assertOk();

        $this->assertNull(User::withoutGlobalScopes()->withTrashed()->find($user->id));
    }

    public function test_a_project_change_also_prepares_one_email_draft_for_review_and_sends_nothing(): void
    {
        config(['services.deepseek.key' => null]);
        Mail::fake();

        $this->actingAsMember($this->admin);
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/activities", ['activity_date' => now()->toDateString(), 'title' => 'Build login', 'status' => 'in_progress', 'progress_percent' => 20])->assertCreated();
        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/documents", ['title' => 'Spec', 'file' => UploadedFile::fake()->create('spec.pdf', 5)])->assertCreated();

        Mail::assertNothingSent();
        $drafts = ClientMessage::withoutGlobalScopes()->where('project_id', $this->project->id)->where('reason', 'project_update')->get();
        $this->assertCount(1, $drafts);
        $this->assertSame('draft', $drafts[0]->status);
        $this->assertSame('email', $drafts[0]->channel);
        $this->assertSame('casey@acme.test', $drafts[0]->to_address);
        $this->assertStringContainsString('Build login', $drafts[0]->body);
        $this->assertStringContainsString('New document: Spec', $drafts[0]->body);

        // A person reviews and sends it.
        $this->postJson("/api/v1/tracker/messages/{$drafts[0]->id}/send")->assertOk()->assertJsonPath('data.status', 'sent');
        Mail::assertSent(ClientUpdateMail::class, 1);
    }

    public function test_email_drafts_can_be_switched_off_in_settings(): void
    {
        config(['services.deepseek.key' => null]);
        $this->tenant->saveWorkspaceSettings(['email_updates' => false]);

        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/activities", ['activity_date' => now()->toDateString(), 'title' => 'Quiet step'])->assertCreated();

        $this->assertSame(0, ClientMessage::withoutGlobalScopes()->where('reason', 'project_update')->count());
    }

    public function test_the_client_can_upload_send_updates_and_raise_requirements_and_the_team_is_notified(): void
    {
        config(['services.deepseek.key' => null]);
        $client = $this->portalUser();
        $staff = $this->makeMember(Role::Developer, $this->tenant);

        $this->actingAsMember($client);
        $base = "/api/v1/portal/projects/{$this->project->id}";

        $this->postJson("{$base}/documents", ['title' => ''])->assertJsonValidationErrors(['title', 'file']);
        $this->postJson("{$base}/documents", ['title' => 'Brand guide', 'note' => 'Latest version', 'file' => UploadedFile::fake()->create('brand.pdf', 20)])->assertCreated()->assertJsonPath('data.from_client', true);
        $this->postJson("{$base}/documents", ['title' => 'Bad', 'file' => UploadedFile::fake()->create('run.exe', 1)])->assertStatus(422);
        $this->postJson("{$base}/updates", ['message' => ''])->assertJsonValidationErrors('message');
        $this->postJson("{$base}/updates", ['message' => 'Logo is on its way tomorrow.'])->assertCreated();
        $this->postJson("{$base}/requirements", ['title' => 'Add dark mode', 'description' => 'For the dashboard'])->assertCreated()->assertJsonPath('data.status', 'pending');

        // Another client's project is out of reach.
        $rival = app(TenantContext::class)->runAs($this->tenant, fn () => Project::create(['name' => 'Rival', 'start_date' => now(), 'due_date' => now()->addDays(3)]));
        $this->postJson("/api/v1/portal/projects/{$rival->id}/updates", ['message' => 'hello there'])->assertNotFound();
        $this->postJson("/api/v1/portal/projects/{$rival->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('a.pdf', 1)])->assertNotFound();

        // Every team member is notified.
        $titles = collect($this->actingAsMember($staff)->getJson('/api/v1/notifications')->assertOk()->json('data'))->pluck('title');
        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'uploaded a document: Brand guide')));
        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'sent an update')));
        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'raised a new requirement: Add dark mode')));

        // The team sees the requirement and the client file on the project, and the whole timeline.
        $detail = $this->getJson("/api/v1/tracker/projects/{$this->project->id}")->assertOk();
        $detail->assertJsonPath('change_requests.0.title', 'Add dark mode')->assertJsonPath('documents.0.from_client', true);
        $events = collect($detail->json('events'));
        $this->assertTrue($events->contains(fn ($e) => $e['actor'] === 'client' && str_contains($e['title'], 'sent an update')));
    }

    public function test_moving_the_stage_and_status_notifies_the_client_and_is_on_both_timelines(): void
    {
        config(['services.deepseek.key' => null]);
        $client = $this->portalUser();

        $this->actingAsMember($this->admin)->patchJson("/api/v1/tracker/projects/{$this->project->id}", ['stage' => 'nonsense'])->assertJsonValidationErrors('stage');
        $this->patchJson("/api/v1/tracker/projects/{$this->project->id}", ['stage' => 'development'])->assertOk()->assertJsonPath('data.stage', 'development');
        $this->patchJson("/api/v1/tracker/projects/{$this->project->id}", ['status' => 'on_hold'])->assertOk();
        $this->patchJson("/api/v1/tracker/projects/{$this->project->id}", ['stage' => 'development'])->assertOk(); // unchanged: no second alert

        $titles = collect($this->actingAsMember($client)->getJson('/api/v1/notifications')->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Project moved to Development'));
        $this->assertTrue($titles->contains('Project marked as on hold'));
        $this->assertSame(1, $titles->filter(fn ($t) => $t === 'Project moved to Development')->count());

        $portal = $this->getJson("/api/v1/portal/projects/{$this->project->id}")->assertOk();
        $portal->assertJsonPath('data.stage', 'development')->assertJsonPath('data.stage_label', 'Development')->assertJsonCount(6, 'stages');
        $this->assertContains('Project moved to Development', collect($portal->json('events'))->pluck('title')->all());
        $this->assertSame('team', collect($portal->json('events'))->firstWhere('title', 'Project moved to Development')['actor']);
    }

    public function test_client_replies_to_a_request_reach_the_team(): void
    {
        $client = $this->portalUser();
        $dependency = app(TenantContext::class)->runAs($this->tenant, fn () => $this->project->clientDependencies()->create(['title' => 'Brand logo', 'requested_on' => now()->toDateString()]));

        $this->actingAsMember($client)->postJson("/api/v1/portal/projects/{$this->project->id}/updates", ['message' => 'Sent by email just now.', 'request_id' => $dependency->id])->assertCreated();

        $titles = collect($this->actingAsMember($this->admin)->getJson('/api/v1/notifications')->json('data'))->pluck('title');
        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'replied about: Brand logo')));
    }
}
