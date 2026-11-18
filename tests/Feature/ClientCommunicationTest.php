<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ClientUpdateMail;
use App\Models\ChatEvaluation;
use App\Models\Client;
use App\Models\ClientDependency;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ClientCommunicationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $admin;

    private Tenant $tenant;

    private Client $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'services.deepseek.key' => null,
            'services.evolution.url' => 'https://evo.test',
            'services.evolution.key' => 'evo-key',
            'services.evolution.instance' => 'spiderplms',
            'services.evolution.webhook_token' => 'hook-secret',
        ]);

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);
        $this->client = Client::factory()->forTenant($this->tenant)->create(['name' => 'Northwind', 'contact_person' => 'Nora', 'email' => 'nora@northwind.test', 'whatsapp_number' => '919876543210']);
        $this->project = Project::factory()->forTenant($this->tenant)->create(['name' => 'Customer Portal', 'client_id' => $this->client->id, 'start_date' => now()->subWeeks(4), 'due_date' => now()->addWeeks(4)]);
    }

    public function test_clients_are_managed_per_workspace_and_whatsapp_numbers_are_normalised(): void
    {
        $other = Client::factory()->create(['name' => 'Foreign Co']);

        $this->actingAsMember($this->admin);
        $created = $this->postJson('/api/v1/clients', ['name' => 'Acme', 'email' => 'a@acme.test', 'whatsapp_number' => '+91 98765-43211'])
            ->assertCreated()->assertJsonPath('data.whatsapp_number', '919876543211')->json('data');

        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissing(['name' => 'Foreign Co']);
        $this->patchJson("/api/v1/clients/{$created['id']}", ['contact_person' => 'Ann'])->assertOk()->assertJsonPath('data.contact_person', 'Ann');
        $this->patchJson("/api/v1/clients/{$other->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->postJson('/api/v1/clients', ['name' => 'X', 'email' => 'not-an-email'])->assertJsonValidationErrors('email');
        $this->deleteJson("/api/v1/clients/{$created['id']}")->assertOk();
    }

    public function test_a_project_can_use_a_client_from_the_master_list_but_not_another_workspaces(): void
    {
        $foreign = Client::factory()->create();

        $this->actingAsMember($this->admin)
            ->postJson('/api/v1/tracker/projects', ['name' => 'New', 'client_id' => $this->client->id, 'start_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString()])
            ->assertCreated()->assertJsonPath('data.client_id', $this->client->id)->assertJsonPath('data.client_name', 'Northwind');

        $this->postJson('/api/v1/tracker/projects', ['name' => 'Bad', 'client_id' => $foreign->id, 'start_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString()])
            ->assertJsonValidationErrors('client_id');
    }

    public function test_a_draft_is_written_without_ai_when_no_key_is_set_and_only_sent_after_review(): void
    {
        Mail::fake();
        $this->actingAsMember($this->admin);

        $draft = $this->postJson("/api/v1/tracker/projects/{$this->project->id}/messages/draft", ['channel' => 'email', 'reason' => 'progress_update'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.ai_generated', false)
            ->assertJsonPath('data.to_address', 'nora@northwind.test')
            ->json('data');

        $this->assertStringContainsString('Customer Portal', $draft['body']);
        $this->assertStringContainsString('Nora', $draft['body']);
        Mail::assertNothingSent();

        $this->patchJson("/api/v1/tracker/messages/{$draft['id']}", ['body' => 'Hello Nora, a reviewed note.'])->assertOk();
        $this->postJson("/api/v1/tracker/messages/{$draft['id']}/send")->assertOk()->assertJsonPath('data.status', 'sent');

        Mail::assertSent(ClientUpdateMail::class, fn (ClientUpdateMail $mail) => $mail->hasTo('nora@northwind.test') && str_contains($mail->body, 'a reviewed note'));
        $this->postJson("/api/v1/tracker/messages/{$draft['id']}/send")->assertStatus(422);
        $this->deleteJson("/api/v1/tracker/messages/{$draft['id']}")->assertStatus(422);
    }

    public function test_deepseek_drafts_use_the_workspace_knowledge_base(): void
    {
        config(['services.deepseek.key' => 'test-key']);
        Http::fake(['evo.test/*' => Http::response(['instance' => ['state' => 'open']]), 'api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['subject' => 'Quick update', 'body' => 'Hi Nora, all on track.'])]]]])]);
        $this->actingAsMember($this->admin)->putJson('/api/v1/workspace/settings', ['knowledge_base' => 'We never promise dates on weekends.', 'tone' => 'friendly'])->assertOk();

        $this->postJson("/api/v1/tracker/projects/{$this->project->id}/messages/draft", ['channel' => 'email', 'reason' => 'status_alert'])
            ->assertCreated()->assertJsonPath('data.ai_generated', true)->assertJsonPath('data.subject', 'Quick update');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'deepseek') && str_contains($request['messages'][0]['content'], 'We never promise dates on weekends.') && str_contains($request['messages'][0]['content'], 'friendly'));
    }

    public function test_a_message_that_cannot_be_delivered_is_marked_failed_with_the_reason(): void
    {
        $this->client->forceFill(['email' => null])->save();
        $this->actingAsMember($this->admin);

        $draft = $this->postJson("/api/v1/tracker/projects/{$this->project->id}/messages/draft", ['channel' => 'email', 'reason' => 'custom', 'instruction' => 'Please confirm the meeting.'])->json('data');
        $this->postJson("/api/v1/tracker/messages/{$draft['id']}/send")->assertStatus(422)->assertJsonPath('data.status', 'failed');
        $this->assertStringContainsString('no email address', ClientMessage::find($draft['id'])->error);
    }

    public function test_whatsapp_messages_are_sent_through_the_evolution_api(): void
    {
        Http::fake(['evo.test/message/sendText/spiderplms' => Http::response(['key' => ['id' => 'EVO-OUT-1']])]);
        $this->actingAsMember($this->admin);

        $draft = $this->postJson("/api/v1/tracker/projects/{$this->project->id}/messages/draft", ['channel' => 'whatsapp', 'reason' => 'progress_update'])
            ->assertCreated()->assertJsonPath('data.to_address', '919876543210')->json('data');

        $this->postJson("/api/v1/tracker/messages/{$draft['id']}/send")->assertOk()->assertJsonPath('data.status', 'sent');
        Http::assertSent(fn ($request) => $request['number'] === '919876543210' && $request->hasHeader('apikey', 'evo-key'));
        $this->assertSame('EVO-OUT-1', ClientMessage::find($draft['id'])->provider_message_id);
    }

    public function test_a_number_not_on_whatsapp_fails_with_a_plain_message(): void
    {
        Http::fake(['evo.test/*' => Http::response(['status' => 400, 'response' => ['message' => [['jid' => '1@s.whatsapp.net', 'exists' => false]]]], 400)]);
        $this->actingAsMember($this->admin);

        $draft = $this->postJson("/api/v1/tracker/projects/{$this->project->id}/messages/draft", ['channel' => 'whatsapp', 'reason' => 'custom', 'instruction' => 'Hi'])->json('data');
        $this->postJson("/api/v1/tracker/messages/{$draft['id']}/send")->assertStatus(422)->assertJsonPath('data.status', 'failed');
        $this->assertStringContainsString('not on WhatsApp', ClientMessage::find($draft['id'])->error);
    }

    public function test_the_whatsapp_webhook_needs_the_token_and_stores_client_replies_with_an_evaluation(): void
    {
        $payload = ['event' => 'messages.upsert', 'instance' => 'spiderplms', 'data' => [
            'key' => ['remoteJid' => '919876543210@s.whatsapp.net', 'fromMe' => false, 'id' => 'EVO-IN-1'],
            'pushName' => 'Nora',
            'message' => ['conversation' => 'When will the login page be ready?'],
        ]];

        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertForbidden();
        $this->postJson('/api/v1/webhooks/whatsapp?token=wrong', $payload)->assertForbidden();
        $this->assertDatabaseCount('client_messages', 0);

        $this->postJson('/api/v1/webhooks/whatsapp?token=hook-secret', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/whatsapp?token=hook-secret', $payload)->assertOk(); // redelivery is ignored

        // Our own messages, groups and other instances are ignored.
        $own = $payload;
        $own['data']['key'] = ['remoteJid' => '919876543210@s.whatsapp.net', 'fromMe' => true, 'id' => 'EVO-OWN'];
        $group = $payload;
        $group['data']['key'] = ['remoteJid' => '120363@g.us', 'fromMe' => false, 'id' => 'EVO-GRP'];
        $other = $payload;
        $other['instance'] = 'someone-else';
        $other['data']['key']['id'] = 'EVO-OTHER';
        foreach ([$own, $group, $other] as $ignored) {
            $this->postJson('/api/v1/webhooks/whatsapp?token=hook-secret', $ignored)->assertOk();
        }

        $this->assertDatabaseCount('client_messages', 1);
        $this->assertDatabaseHas('client_messages', ['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'direction' => 'in', 'status' => 'received', 'body' => 'When will the login page be ready?']);

        $evaluation = ChatEvaluation::withoutGlobalScopes()->where('project_id', $this->project->id)->first();
        $this->assertNotNull($evaluation);
        $this->assertSame('fallback', $evaluation->source);

        $this->actingAsMember($this->admin)->getJson("/api/v1/tracker/projects/{$this->project->id}/chat")
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('evaluation.messages_considered', 1);
    }

    public function test_the_webhook_understands_hidden_number_ids_and_extended_text(): void
    {
        $payload = ['event' => 'MESSAGES_UPSERT', 'instance' => 'spiderplms', 'data' => [
            'key' => ['remoteJid' => '1234@lid', 'senderPn' => '919876543210@s.whatsapp.net', 'fromMe' => false, 'id' => 'EVO-LID'],
            'message' => ['extendedTextMessage' => ['text' => 'Replying with a quote']],
        ]];

        $this->postJson('/api/v1/webhooks/whatsapp?token=hook-secret', $payload)->assertOk();

        $this->assertDatabaseHas('client_messages', ['client_id' => $this->client->id, 'body' => 'Replying with a quote', 'direction' => 'in']);
    }

    public function test_deepseek_evaluates_the_chat_and_suggests_a_reply(): void
    {
        config(['services.deepseek.key' => 'test-key']);
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'Client is asking about the login page date.', 'sentiment' => 'urgent', 'pending_items' => ['Login page date'], 'suggested_reply' => 'It will be ready Friday.',
        ])]]]])]);
        app(TenantContext::class)->set($this->tenant);
        ClientMessage::create(['project_id' => $this->project->id, 'client_id' => $this->client->id, 'channel' => 'whatsapp', 'direction' => 'in', 'body' => 'Any update?', 'status' => 'received', 'provider_message_id' => 'wamid.X']);

        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$this->project->id}/chat/evaluate")
            ->assertOk()->assertJsonPath('evaluation.sentiment', 'urgent')->assertJsonPath('evaluation.source', 'deepseek')->assertJsonPath('evaluation.pending_items.0', 'Login page date');
    }

    public function test_only_organization_admins_change_settings_and_messages_stay_inside_the_workspace(): void
    {
        $developer = $this->makeMember(Role::Developer, $this->tenant);
        $this->actingAsMember($developer)->putJson('/api/v1/workspace/settings', ['tone' => 'formal'])->assertForbidden();
        $this->getJson('/api/v1/workspace/settings')->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('data.tone', 'professional');

        $foreignTenant = Tenant::factory()->create();
        $foreignProject = Project::factory()->forTenant($foreignTenant)->create();
        $foreignMessage = app(TenantContext::class)->runAs($foreignTenant, fn () => ClientMessage::create(['project_id' => $foreignProject->id, 'channel' => 'email', 'body' => 'secret', 'status' => 'draft']));

        $this->actingAsMember($this->admin);
        $this->getJson('/api/v1/tracker/messages')->assertOk()->assertJsonMissing(['body' => 'secret']);
        $this->postJson("/api/v1/tracker/messages/{$foreignMessage->id}/send")->assertNotFound();
        $this->postJson("/api/v1/tracker/projects/{$foreignProject->id}/messages/draft", ['channel' => 'email', 'reason' => 'custom'])->assertNotFound();
    }

    public function test_the_reminder_command_drafts_for_overdue_client_inputs_once_and_never_sends(): void
    {
        Mail::fake();
        app(TenantContext::class)->runAs($this->tenant, fn () => ClientDependency::create([
            'project_id' => $this->project->id, 'title' => 'Brand assets', 'requested_on' => now()->subWeeks(2), 'expected_on' => now()->subDays(5),
        ]));

        $this->artisan('plms:draft-reminders')->assertSuccessful();
        $this->artisan('plms:draft-reminders')->assertSuccessful();

        $drafts = ClientMessage::withoutGlobalScopes()->where('project_id', $this->project->id)->get();
        $this->assertCount(1, $drafts);
        $this->assertSame('draft', $drafts[0]->status);
        $this->assertSame('dependency_overdue', $drafts[0]->reason);
        $this->assertStringContainsString('Brand assets', $drafts[0]->body);
        Mail::assertNothingSent();

        // A workspace can switch the automation off.
        ClientMessage::withoutGlobalScopes()->delete();
        $this->tenant->saveWorkspaceSettings(['auto_draft' => false]);
        $this->artisan('plms:draft-reminders')->assertSuccessful();
        $this->assertDatabaseCount('client_messages', 0);
    }
}
