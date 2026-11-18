<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectAiAnalysis;
use App\Models\ProjectEvent;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectOnboardingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;
use ZipArchive;

class ProjectOnboardingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $admin;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('local');
        config(['services.deepseek.key' => null]);

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);
        $this->tenant->update(['plan_id' => Plan::where('code', 'enterprise')->value('id')]);
    }

    /** A real .docx (a zip with word/document.xml) holding the given paragraphs. */
    private function docx(array $paragraphs, string $name = 'brief.docx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'plms').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $body = implode('', array_map(fn ($p) => '<w:p><w:r><w:t>'.htmlspecialchars($p, ENT_XML1).'</w:t></w:r></w:p>', $paragraphs));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="x"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function brief(): array
    {
        return [
            'Acme Online Store',
            'We need an e-commerce website where customers can browse products, add them to a cart and pay with Razorpay. Built with Laravel and React on PostgreSQL.',
            '1. Product catalogue',
            '2. Shopping cart and checkout',
            '3. Admin dashboard',
            'Deliverables',
            '- Source code',
            '- Admin user guide',
        ];
    }

    private function aiAnswer(): array
    {
        return [
            'project_name' => 'Acme Online Store', 'client_name' => 'Acme Retail', 'project_type' => 'E-commerce', 'summary' => 'An online store.',
            'scope' => ['Catalogue', 'Checkout'], 'modules' => [['name' => 'Catalogue', 'description' => 'Browse products']],
            'deliverables' => ['Source code'], 'technologies' => ['Laravel', 'React'], 'complexity' => 'high',
            'estimated_working_days' => 40, 'confidence' => 82, 'assumptions' => ['Client supplies product data'], 'risks' => ['Payment approval'],
            'steps' => [
                ['title' => 'Requirement analysis', 'description' => 'Confirm scope', 'stage' => 'requirements', 'working_days' => 4, 'deliverables' => ['Signed scope']],
                ['title' => 'UI/UX design', 'description' => 'Wireframes', 'stage' => 'design', 'working_days' => 8, 'deliverables' => []],
                ['title' => 'Backend development', 'description' => 'APIs', 'stage' => 'development', 'working_days' => 14, 'deliverables' => []],
                ['title' => 'Frontend development', 'description' => 'Screens', 'stage' => 'development', 'working_days' => 8, 'deliverables' => []],
                ['title' => 'Testing and bug fixing', 'description' => 'QA', 'stage' => 'testing', 'working_days' => 4, 'deliverables' => []],
                ['title' => 'Final delivery', 'description' => 'Go live', 'stage' => 'handover', 'working_days' => 2, 'deliverables' => ['Live site']],
            ],
        ];
    }

    public function test_a_docx_is_read_by_the_ai_and_returned_for_review_without_creating_a_project(): void
    {
        config(['services.deepseek.key' => 'k']);
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->aiAnswer())]]]])]);

        $response = $this->actingAsMember($this->admin)->post('/api/v1/tracker/onboarding/analyse', ['file' => $this->docx($this->brief())], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.source', 'deepseek')
            ->assertJsonPath('data.confidence', 82)
            ->assertJsonPath('data.extracted.project_name', 'Acme Online Store')
            ->assertJsonPath('data.extracted.technologies.0', 'Laravel')
            ->assertJsonPath('data.estimate.working_days', 40)
            ->assertJsonPath('data.estimate.complexity', 'high');

        $steps = $response->json('data.estimate.steps');
        $this->assertCount(6, $steps);
        $this->assertSame(40, array_sum(array_column($steps, 'working_days')));
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseHas('project_ai_analyses', ['tenant_id' => $this->tenant->id, 'project_id' => null, 'original_name' => 'brief.docx']);

        // The document text really reached the AI.
        Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Shopping cart and checkout'));
    }

    public function test_without_ai_a_pdf_is_read_with_rules_and_flagged_as_a_basic_estimate(): void
    {
        $html = '<h1>Acme Online Store</h1><p>An e-commerce website with a shopping cart, built in Laravel and React with PostgreSQL. Payments use Razorpay and customers can track orders and download invoices.</p>';
        $path = tempnam(sys_get_temp_dir(), 'plms').'.pdf';
        file_put_contents($path, Pdf::loadHTML($html)->output());

        $data = $this->actingAsMember($this->admin)->post('/api/v1/tracker/onboarding/analyse', ['file' => new UploadedFile($path, 'store.pdf', null, null, true)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.source', 'fallback')->json('data');

        $this->assertLessThanOrEqual(40, $data['confidence']);
        $this->assertSame('E-commerce', $data['extracted']['project_type']);
        $this->assertContains('Laravel', $data['extracted']['technologies']);
        $this->assertContains('Razorpay', $data['extracted']['technologies']);
        $this->assertCount(6, $data['estimate']['steps']);
        $this->assertSame($data['estimate']['working_days'], array_sum(array_column($data['estimate']['steps'], 'working_days')));
        $this->assertStringContainsString('initial estimate', $data['estimate']['notes']);
    }

    public function test_unreadable_or_unsupported_files_are_rejected_with_a_clear_message_and_nothing_is_kept(): void
    {
        $this->actingAsMember($this->admin);

        $this->post('/api/v1/tracker/onboarding/analyse', ['file' => UploadedFile::fake()->createWithContent('tiny.txt', 'too short')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'readable text'));
        $this->post('/api/v1/tracker/onboarding/analyse', ['file' => UploadedFile::fake()->createWithContent('app.exe', str_repeat('x', 200))], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->post('/api/v1/tracker/onboarding/analyse', [], ['Accept' => 'application/json'])->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('project_ai_analyses', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_reviewed_plan_creates_a_project_with_steps_fitted_to_its_dates_and_files_the_document(): void
    {
        config(['services.deepseek.key' => 'k']);
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->aiAnswer())]]]])]);
        $this->actingAsMember($this->admin);

        $analysis = $this->post('/api/v1/tracker/onboarding/analyse', ['file' => $this->docx($this->brief())], ['Accept' => 'application/json'])->json('data');

        // The user shortened the project: 20 working days (Mon 23 Nov to Fri 18 Dec 2026) instead of the AI's 40.
        $payload = [
            'name' => 'Acme Online Store', 'start_date' => '2026-11-23', 'due_date' => '2026-12-18', 'total_working_days' => 20,
            'analysis_id' => $analysis['id'],
            'steps' => array_map(fn ($s) => ['title' => $s['title'], 'description' => $s['description'], 'stage' => $s['stage'], 'working_days' => $s['working_days'], 'deliverables' => $s['deliverables']], $analysis['estimate']['steps']),
        ];

        $project = $this->postJson('/api/v1/tracker/projects', $payload)->assertCreated()->json('data');

        $steps = ProjectMilestone::where('project_id', $project['id'])->orderBy('sort_order')->get();
        $this->assertCount(6, $steps);
        $this->assertSame(20, (int) $steps->sum('estimated_days'));
        $this->assertSame('2026-11-23', $steps->first()->planned_start->toDateString());
        $this->assertSame('2026-12-18', $steps->last()->due_date->toDateString());
        $this->assertTrue($steps->every(fn ($s) => $s->source === 'ai' && $s->estimated_days >= 1));
        foreach ($steps->slice(1) as $i => $step) { // each step starts the working day after the previous one ends
            $this->assertTrue($step->planned_start->gt($steps[$i - 1]->due_date));
            $this->assertTrue($step->planned_start->lte($step->due_date));
        }
        $this->assertSame(['Signed scope'], $steps->first()->deliverables);

        $this->assertDatabaseHas('project_documents', ['project_id' => $project['id'], 'name' => 'brief.docx', 'visible_to_client' => false]);
        $this->assertSame($project['id'], ProjectAiAnalysis::find($analysis['id'])->project_id);
        Storage::disk('local')->assertExists(ProjectAiAnalysis::find($analysis['id'])->path);

        // The ledger: what happened, when, with data. Internal lines never reach the client portal.
        $created = ProjectEvent::where('project_id', $project['id'])->where('event_type', 'project.created')->first();
        $this->assertNotNull($created->occurred_at);
        $this->assertFalse($created->visible_to_client);
        $this->assertTrue($created->payload['from_document']);
        $this->assertSame('project', $created->subject_type);
        $this->assertDatabaseHas('project_events', ['project_id' => $project['id'], 'event_type' => 'ai.analysis_applied', 'visible_to_client' => false]);

        // The same analysis cannot start a second project, and another workspace cannot use it.
        $this->postJson('/api/v1/tracker/projects', ['name' => 'Again'] + $payload)->assertJsonValidationErrors('analysis_id');
        $other = $this->makeMember(Role::OrgAdmin);
        $this->actingAsMember($other)->postJson('/api/v1/tracker/projects', ['name' => 'Steal'] + $payload)->assertJsonValidationErrors('analysis_id');
    }

    public function test_a_project_without_a_document_still_gets_the_default_plan_and_a_ledger_start(): void
    {
        $project = $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', ['name' => 'Manual', 'start_date' => '2026-11-23', 'due_date' => '2026-12-18'])
            ->assertCreated()->json('data');

        $this->assertSame(6, ProjectMilestone::where('project_id', $project['id'])->count());
        $this->assertDatabaseHas('project_events', ['project_id' => $project['id'], 'event_type' => 'project.created']);
        $this->assertDatabaseMissing('project_events', ['project_id' => $project['id'], 'event_type' => 'ai.analysis_applied']);
    }

    public function test_step_changes_are_stamped_and_recorded_as_structured_events(): void
    {
        $project = $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', ['name' => 'Ledger', 'start_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->addDays(40)->toDateString()])->json('data');
        $step = ProjectMilestone::where('project_id', $project['id'])->orderBy('sort_order')->skip(1)->first();
        $developer = $this->makeMember(Role::Developer, $this->tenant);

        $this->patchJson("/api/v1/tracker/milestones/{$step->id}", ['status' => 'in_progress', 'assigned_to' => $developer->id])->assertOk();
        $step->refresh();
        $this->assertNotNull($step->started_at);
        $this->assertSame($developer->id, $step->assigned_to);
        $this->assertDatabaseHas('project_events', ['project_id' => $project['id'], 'event_type' => 'milestone.started', 'subject_type' => 'project_milestone', 'subject_id' => $step->id]);
        $assigned = ProjectEvent::where('event_type', 'milestone.assigned')->where('subject_id', $step->id)->first();
        $this->assertSame($developer->id, $assigned->payload['to']);
        $this->assertFalse($assigned->visible_to_client);

        $this->patchJson("/api/v1/tracker/milestones/{$step->id}", ['status' => 'completed'])->assertOk();
        $step->refresh();
        $this->assertNotNull($step->completed_at);
        $done = ProjectEvent::where('event_type', 'milestone.completed')->where('subject_id', $step->id)->first();
        $this->assertArrayHasKey('days_late', $done->payload);
        $this->assertNotNull($done->occurred_at);

        $this->patchJson("/api/v1/tracker/milestones/{$step->id}", ['status' => 'pending'])->assertOk();
        $step->refresh();
        $this->assertNull($step->started_at);
        $this->assertNull($step->completed_at);
    }

    public function test_a_step_can_only_be_assigned_to_active_staff_of_the_same_workspace(): void
    {
        $project = $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', ['name' => 'Owners', 'start_date' => '2026-11-23', 'due_date' => '2026-12-18'])->json('data');
        $step = ProjectMilestone::where('project_id', $project['id'])->first();
        $outsider = $this->makeMember(Role::Developer);

        $this->patchJson("/api/v1/tracker/milestones/{$step->id}", ['assigned_to' => $outsider->id])->assertJsonValidationErrors('assigned_to');
    }

    public function test_working_days_are_shared_over_steps_exactly_and_never_below_one_day(): void
    {
        $service = app(ProjectOnboardingService::class);

        foreach ([[[10, 15, 45, 20, 5, 5], 60], [[1, 1, 1], 2], [[5, 5], 1], [[3, 3, 3], 10], [[1], 7]] as [$weights, $total]) {
            $days = $service->fitDays($weights, $total);
            $this->assertSame(max($total, count($weights)), array_sum($days), json_encode([$weights, $total, $days]));
            $this->assertSame(count($weights), count($days));
            $this->assertGreaterThanOrEqual(1, min($days));
        }
    }

    public function test_the_project_plan_limit_blocks_document_analysis(): void
    {
        $this->tenant->update(['plan_id' => Plan::where('code', 'starter')->value('id')]);
        Project::factory()->forTenant($this->tenant)->count(10)->create();

        $this->actingAsMember($this->admin)->post('/api/v1/tracker/onboarding/analyse', ['file' => $this->docx($this->brief())], ['Accept' => 'application/json'])->assertStatus(402);
    }
}
