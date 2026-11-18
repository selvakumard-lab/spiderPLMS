<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ClientUpdateMail;
use App\Mail\LoginCodeMail;
use App\Mail\PortalAccessMail;
use App\Mail\ProjectCompletedMail;
use App\Mail\ProjectCreatedMail;
use App\Models\Client;
use App\Models\ProjectEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientEmailNotificationsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);
        $this->tenant->update(['name' => 'Northwind Digital', 'contact_email' => 'hello@northwind.test']);
        $this->client = app(TenantContext::class)->runAs($this->tenant, fn () => Client::create(['name' => 'Acme Ltd', 'contact_person' => 'Casey Client', 'email' => 'casey@acme.test']));
    }

    private function createProject(array $overrides = []): array
    {
        return $this->actingAsMember($this->admin)->postJson('/api/v1/tracker/projects', $overrides + [
            'name' => 'Acme Website', 'client_id' => $this->client->id, 'start_date' => '2026-11-23', 'due_date' => '2026-12-18', 'total_working_days' => 20,
        ])->assertCreated()->json('data');
    }

    public function test_giving_portal_access_emails_the_client_once(): void
    {
        Mail::fake();

        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk();

        Mail::assertSent(PortalAccessMail::class, 1);
        Mail::assertSent(PortalAccessMail::class, fn (PortalAccessMail $mail) => $mail->hasTo('casey@acme.test')
            && $mail->workspaceName === 'Northwind Digital'
            && $mail->clientName === 'Casey Client'
            && $mail->signInUrl === url('/'.$this->tenant->slug)
            && $mail->hasReplyTo('hello@northwind.test'));

        // Pressing the button again changes nothing and sends nothing.
        $this->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk();
        Mail::assertSent(PortalAccessMail::class, 1);
        $this->assertDatabaseHas('activity_logs', ['event' => 'client.portal.invited']);
    }

    public function test_a_failed_email_never_blocks_giving_access(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk()->assertJsonPath('data.has_portal_access', true);
        $this->assertDatabaseHas('activity_logs', ['event' => 'client.portal.invite_failed']);
    }

    public function test_creating_a_project_emails_the_client_the_plan(): void
    {
        Mail::fake();
        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk();
        Mail::fake();

        $project = $this->createProject();

        Mail::assertSent(ProjectCreatedMail::class, 1);
        Mail::assertSent(ProjectCreatedMail::class, function (ProjectCreatedMail $mail) use ($project) {
            return $mail->hasTo('casey@acme.test')
                && $mail->projectName === 'Acme Website'
                && $mail->projectCode === $project['code']
                && $mail->startDate === '23 Nov 2026'
                && $mail->dueDate === '18 Dec 2026'
                && $mail->workingDays === 20
                && $mail->stepCount === 6
                && count($mail->steps) === 6
                && $mail->steps[0]['title'] === 'Requirements'
                && $mail->portalUrl === url('/'.$this->tenant->slug);
        });
        $this->assertDatabaseHas('project_events', ['project_id' => $project['id'], 'event_type' => 'email.project_created', 'visible_to_client' => false]);
    }

    public function test_the_project_email_has_no_portal_link_until_the_client_has_access_and_needs_a_client_email(): void
    {
        Mail::fake();
        $this->createProject();
        Mail::assertSent(ProjectCreatedMail::class, fn (ProjectCreatedMail $mail) => $mail->portalUrl === null);

        Mail::fake();
        $this->createProject(['client_id' => null, 'client_name' => 'Someone']);
        $noEmail = app(TenantContext::class)->runAs($this->tenant, fn () => Client::create(['name' => 'No Email Co']));
        $this->createProject(['client_id' => $noEmail->id]);
        Mail::assertNothingSent();
    }

    public function test_marking_a_project_completed_emails_the_client_once(): void
    {
        $project = $this->createProject(['start_date' => now()->subDays(9)->toDateString(), 'due_date' => now()->addDays(3)->toDateString()]);
        Mail::fake();

        $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['status' => 'completed'])->assertOk();

        Mail::assertSent(ProjectCompletedMail::class, 1);
        Mail::assertSent(ProjectCompletedMail::class, fn (ProjectCompletedMail $mail) => $mail->hasTo('casey@acme.test')
            && $mail->projectName === 'Acme Website'
            && $mail->verdict === 'early'
            && $mail->daysDifference === 3
            && $mail->deliveredOn === today()->format('j M Y')
            && $mail->stepsTotal === 6);
        $this->assertDatabaseHas('project_events', ['project_id' => $project['id'], 'event_type' => 'email.project_completed']);

        // Saving it as completed again, or any other change, sends nothing more.
        $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['status' => 'completed'])->assertOk();
        $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['name' => 'Acme Website v2'])->assertOk();
        Mail::assertSent(ProjectCompletedMail::class, 1);
    }

    public function test_the_completion_email_says_whether_delivery_was_on_time_or_late(): void
    {
        foreach ([[-0, 'on_time', 0], [5, 'late', 5]] as [$daysBeforeDue, $verdict, $difference]) {
            Mail::fake();
            $project = $this->createProject(['start_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays($daysBeforeDue)->toDateString()]);
            $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['status' => 'completed'])->assertOk();

            Mail::assertSent(ProjectCompletedMail::class, fn (ProjectCompletedMail $mail) => $mail->verdict === $verdict && $mail->daysDifference === $difference);
        }
    }

    public function test_other_status_changes_do_not_send_the_completion_email(): void
    {
        $project = $this->createProject();
        Mail::fake();

        $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['status' => 'on_hold'])->assertOk();
        $this->patchJson("/api/v1/tracker/projects/{$project['id']}", ['status' => 'cancelled'])->assertOk();

        Mail::assertNotSent(ProjectCompletedMail::class);
    }

    public function test_a_failed_project_email_is_recorded_and_does_not_break_the_request(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $project = $this->createProject();

        $this->assertDatabaseHas('project_events', ['project_id' => $project['id'], 'event_type' => 'email.project_created_failed']);
        $this->assertTrue(ProjectEvent::where('project_id', $project['id'])->where('event_type', 'email.project_created_failed')->value('visible_to_client') === false);
    }

    public function test_every_email_renders_with_the_shared_design_the_logo_and_a_plain_text_version(): void
    {
        $common = ['senderName' => 'Northwind Digital', 'appName' => 'Spider PLMS', 'supportEmail' => 'help@northwind.test'];
        $mails = [
            new PortalAccessMail('Casey', 'Northwind Digital', 'https://app.test/northwind', 'casey@acme.test', ...$common),
            new ProjectCreatedMail('Casey', 'Northwind Digital', 'Acme Website', 'PRJ-0001', '23 Nov 2026', '18 Dec 2026', 20, [['title' => 'Requirements', 'due' => '27 Nov']], 6, 'https://app.test/northwind', ...$common),
            new ProjectCompletedMail('Casey', 'Northwind Digital', 'Acme Website', 'PRJ-0001', '23 Nov 2026', '17 Dec 2026', '18 Dec 2026', 25, 'early', 1, 6, 6, 'https://app.test/northwind', ...$common),
            new LoginCodeMail('482913', 'Spider PLMS', 'Northwind Digital', 10, 'help@northwind.test', 'https://app.test/northwind'),
            new ClientUpdateMail('Weekly update', "Hello\nAll on track.", 'Northwind Digital', 'Project Manager', 'Acme Website'),
        ];

        foreach ($mails as $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('@keyframes', $html, $mail::class);
            $this->assertStringContainsString('prefers-reduced-motion', $html, $mail::class);
            $this->assertStringContainsString('prefers-color-scheme: dark', $html, $mail::class);
            $this->assertMatchesRegularExpression('/<img src="(cid:|data:image\/png;base64)/', $html, $mail::class.' shows the logo');
            $this->assertStringContainsString('Northwind Digital', $html, $mail::class);
            $this->assertStringNotContainsString('{{', $html, $mail::class);
        }

        $html = $mails[0]->render();
        $this->assertStringContainsString('Welcome, Casey', $html);
        $this->assertStringContainsString('https://app.test/northwind', $html);
        $this->assertStringContainsString('casey@acme.test', $html);

        $this->assertStringContainsString('Project delivered', $mails[2]->render());
        $this->assertStringContainsString('ahead of schedule', $mails[2]->render());
        $this->assertStringContainsString('482913', $mails[3]->render());

        foreach (['portal-access', 'project-created', 'project-completed'] as $i => $view) {
            $text = view("emails.{$view}-text", (array) $mails[$i])->render();
            $this->assertStringContainsString('Northwind Digital', $text);
            $this->assertStringNotContainsString('<', $text);
        }
    }

    public function test_a_sent_email_attaches_the_logo_inline_so_it_shows_without_loading_remote_images(): void
    {
        Mail::mailer('array')->to('casey@acme.test')->send(new PortalAccessMail('Casey', 'Northwind', 'https://app.test/n', 'casey@acme.test', 'Team', 'Spider PLMS', 'help@n.test'));

        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->toString();

        $this->assertStringContainsString('src=3D"cid:', $sent);
        $this->assertStringContainsString('Content-ID:', $sent);
        $this->assertStringContainsString('image/png', $sent);
        $this->assertStringContainsString('text/plain', $sent);
    }

    public function test_sign_in_code_emails_share_one_branded_design_with_wording_for_each_role(): void
    {
        foreach ([['platform', 'Super Admin', 'administration area'], ['team', 'Team member', 'Northwind Digital workspace'], ['client', 'Client portal', 'client portal at Northwind Digital']] as [$audience, $label, $phrase]) {
            $mail = new LoginCodeMail('482913', 'Spider PLMS', 'Northwind Digital', 10, 'help@northwind.test', 'https://app.test/northwind', $audience, 'person@example.test', '18 Nov 2026, 4:30 PM UTC', '203.0.113.7');
            $html = $mail->render();

            $this->assertStringContainsString($label, $html, $audience);
            $this->assertStringContainsString($phrase, $html, $audience);
            $this->assertSame(6, substr_count($html, 'class="m-digit"'), "$audience shows one tile per digit");
            $this->assertMatchesRegularExpression('/<img src="(cid:|data:image\/png;base64)[^"]+" width="44"/', $html, $audience.' shows the logo in the header');
            foreach (['person@example.test', '203.0.113.7', '18 Nov 2026, 4:30 PM UTC', 'Keep your account safe', 'Valid for', 'Open sign-in page', '482913'] as $needle) {
                $this->assertStringContainsString($needle, $html, "$audience: $needle");
            }

            $text = view('emails.login-code-text', (array) $mail)->render();
            $this->assertStringContainsString('482913', $text);
            $this->assertStringContainsString('203.0.113.7', $text);
        }
    }

    public function test_the_sign_in_email_knows_who_is_signing_in_and_from_where(): void
    {
        Mail::fake();
        $super = User::factory()->superAdmin()->create();
        $member = $this->makeMember(Role::Developer, $this->tenant);
        $this->actingAsMember($this->admin)->postJson("/api/v1/clients/{$this->client->id}/portal-access")->assertOk();
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/otp/request', ['email' => $super->email])->assertOk();
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $this->tenant->slug, 'email' => $member->email])->assertOk();
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $this->tenant->slug, 'email' => 'casey@acme.test'])->assertOk();

        foreach ([[$super->email, 'platform'], [$member->email, 'team'], ['casey@acme.test', 'client']] as [$email, $audience]) {
            Mail::assertSent(LoginCodeMail::class, fn (LoginCodeMail $mail) => $mail->hasTo($email)
                && $mail->audience === $audience
                && $mail->recipientEmail === $email
                && $mail->ipAddress === '127.0.0.1'
                && $mail->requestedAt !== '');
        }
    }

    public function test_user_content_in_emails_is_escaped(): void
    {
        $html = (new ProjectCreatedMail('<b>Casey</b>', 'Northwind', '<script>alert(1)</script>', 'PRJ-1', '1 Jan 2027', '1 Feb 2027', null, [], 0, null, 'Team', 'Spider PLMS', 'x@y.test'))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
