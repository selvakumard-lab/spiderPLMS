<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ProjectTrackerTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->admin = $this->makeMember(Role::OrgAdmin);
    }

    /** @return array<string, mixed> */
    private function createProject(array $overrides = []): array
    {
        return $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', $overrides + [
            'name' => 'Website Revamp',
            'client_name' => 'Acme Corp',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(40)->toDateString(),
            'total_working_days' => 40,
        ])->assertCreated()->json('data');
    }

    public function test_a_project_can_be_onboarded_and_listed(): void
    {
        $project = $this->createProject();

        $this->assertSame('on_time', $project['tracking_status']);
        $this->getJson('/api/v1/tracker/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/tracker/projects/{$project['id']}")->assertOk()->assertJsonPath('summary.status', 'on_time');
    }

    public function test_due_date_cannot_precede_start_date(): void
    {
        $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', [
            'name' => 'Bad', 'start_date' => '2026-05-10', 'due_date' => '2026-05-01',
        ])->assertJsonValidationErrors('due_date');
    }

    public function test_activities_are_logged_and_progress_drives_the_status(): void
    {
        $project = $this->createProject(['start_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->addDays(20)->toDateString()]);

        // Halfway through the schedule but only 10% done -> clearly behind.
        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", [
            'activity_date' => now()->toDateString(), 'title' => 'Wireframes', 'hours_spent' => 16, 'estimated_hours' => 8,
            'status' => 'in_progress', 'progress_percent' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.days_spent', 2)
            ->assertJsonPath('summary.status', 'significantly_delayed')
            ->assertJsonPath('summary.progress_percent', 10)
            ->assertJsonPath('summary.pending_activities', 1);

        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", [
            'activity_date' => now()->toDateString(), 'title' => 'Build', 'hours_spent' => 8, 'status' => 'completed', 'progress_percent' => 55,
        ])->assertCreated()->assertJsonPath('summary.status', 'on_time')->assertJsonPath('summary.completed_activities', 1);

        $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.tracking_status', 'completed')->assertJsonPath('data.progress', 100);
    }

    public function test_deepseek_estimates_the_next_step_and_falls_back_without_a_key(): void
    {
        $project = $this->createProject();

        // No key configured: rule-based estimate.
        config(['services.deepseek.key' => null]);
        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", [
            'activity_date' => now()->toDateString(), 'title' => 'Step 1', 'hours_spent' => 6, 'progress_percent' => 20,
        ])->assertJsonPath('ai_source', 'fallback')->assertJsonPath('data.next_step_estimate_hours', 6);

        config(['services.deepseek.key' => 'test-key']);
        Http::fake(['api.deepseek.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode(['next_step_hours' => 12.5, 'summary' => 'Slightly behind.'])]]]])
            ->push('boom', 500)]);

        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", [
            'activity_date' => now()->toDateString(), 'title' => 'Step 2', 'hours_spent' => 4, 'progress_percent' => 30,
        ])->assertJsonPath('ai_source', 'deepseek')->assertJsonPath('data.next_step_estimate_hours', 12.5)->assertJsonPath('data.ai_analysis', 'Slightly behind.');

        // A failing API must not break logging work.
        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", [
            'activity_date' => now()->toDateString(), 'title' => 'Step 3', 'hours_spent' => 4, 'progress_percent' => 35,
        ])->assertCreated()->assertJsonPath('ai_source', 'fallback');
    }

    public function test_approved_change_requests_move_the_revised_deadline(): void
    {
        $project = $this->createProject(['due_date' => '2030-01-07']); // a Monday

        $change = $this->postJson("/api/v1/tracker/projects/{$project['id']}/change-requests", [
            'title' => 'Add blog', 'submitted_on' => '2029-12-20', 'additional_days' => 5, 'additional_hours' => 40,
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('summary.pending_requirements', 1)->json('data');

        $this->assertNull($this->getJson("/api/v1/tracker/projects/{$project['id']}")->json('data.revised_due_date'));

        $this->patchJson("/api/v1/tracker/change-requests/{$change['id']}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('summary.revised_due_date', '2030-01-14')
            ->assertJsonPath('summary.original_due_date', '2030-01-07')
            ->assertJsonPath('summary.additional_work_hours', 40);

        $this->assertNotNull($this->getJson("/api/v1/tracker/projects/{$project['id']}")->json('data.revised_due_date'));

        $this->patchJson("/api/v1/tracker/change-requests/{$change['id']}", ['status' => 'rejected'])
            ->assertJsonPath('summary.revised_due_date', null);
    }

    public function test_client_dependencies_track_delay_days(): void
    {
        $project = $this->createProject();

        $response = $this->postJson("/api/v1/tracker/projects/{$project['id']}/client-dependencies", [
            'title' => 'Brand assets', 'requested_on' => now()->subDays(14)->toDateString(), 'expected_on' => now()->subDays(7)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.is_pending', true)->assertJsonPath('data.days_delayed', 7)->assertJsonPath('summary.client_pending_items', 1);

        $this->patchJson('/api/v1/tracker/client-dependencies/'.$response->json('data.id'), ['received_on' => now()->subDays(4)->toDateString()])
            ->assertJsonPath('data.days_delayed', 3)->assertJsonPath('data.is_pending', false)->assertJsonPath('summary.client_pending_items', 0);
    }

    public function test_meetings_store_notes_links_and_attachments_privately(): void
    {
        Storage::fake('local');
        $project = $this->createProject();

        $meeting = $this->post("/api/v1/tracker/projects/{$project['id']}/meetings", [
            'meeting_date' => now()->toDateString(), 'title' => 'Kick-off', 'discussion_points' => 'Scope',
            'links' => ['https://example.com/board'], 'files' => [UploadedFile::fake()->create('minutes.pdf', 50), UploadedFile::fake()->image('shot.png')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'data.attachments')->json('data');

        $this->assertSame(['https://example.com/board'], $meeting['links']);

        $attachmentId = $meeting['attachments'][0]['id'];
        $this->get("/api/v1/tracker/attachments/{$attachmentId}/download")->assertOk();

        $this->post("/api/v1/tracker/projects/{$project['id']}/meetings", [
            'meeting_date' => now()->toDateString(), 'title' => 'Bad', 'files' => [UploadedFile::fake()->create('x.php', 1)],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->deleteJson("/api/v1/tracker/meetings/{$meeting['id']}")->assertOk();
        $this->assertDatabaseCount('meeting_attachments', 0);
    }

    public function test_tenants_cannot_see_each_others_projects(): void
    {
        $project = $this->createProject();
        $stranger = $this->makeMember(Role::OrgAdmin);

        $this->actingAsMember($stranger)->getJson('/api/v1/tracker/projects')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tracker/projects/{$project['id']}")->assertNotFound();
        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", ['activity_date' => now()->toDateString(), 'title' => 'x'])->assertNotFound();
    }

    public function test_both_reports_download_as_pdf_and_excel(): void
    {
        $project = $this->createProject();
        $this->postJson("/api/v1/tracker/projects/{$project['id']}/activities", ['activity_date' => now()->toDateString(), 'title' => 'Step', 'hours_spent' => 8, 'progress_percent' => 20]);

        foreach (['progress', 'performance'] as $type) {
            $pdf = $this->get("/api/v1/tracker/projects/{$project['id']}/reports/{$type}?format=pdf");
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent());

            $xlsx = $this->get("/api/v1/tracker/projects/{$project['id']}/reports/{$type}?format=xlsx");
            $xlsx->assertOk();
            $this->assertStringContainsString('.xlsx', $xlsx->headers->get('content-disposition'));
        }

        $this->getJson("/api/v1/tracker/projects/{$project['id']}/reports/nope?format=pdf")->assertNotFound();
    }
}
