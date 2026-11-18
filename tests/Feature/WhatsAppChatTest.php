<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsAppChatTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $admin;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        config([
            'services.evolution.url' => 'https://evo.test',
            'services.evolution.key' => 'evo-key',
            'services.evolution.instance' => 'spiderplms',
            'services.evolution.webhook_token' => 'hook-secret',
        ]);

        $this->admin = $this->makeMember(Role::OrgAdmin);
        $this->tenant = Tenant::find($this->admin->tenant_id);
        $this->client = Client::factory()->forTenant($this->tenant)->create(['name' => 'Northwind', 'whatsapp_number' => '919876543210']);
    }

    private function onPlan(string $code): void
    {
        $this->tenant->update(['plan_id' => Plan::where('code', $code)->value('id')]);
    }

    public function test_only_the_top_plan_includes_whatsapp_chat(): void
    {
        $this->assertSame(['enterprise'], Plan::where('whatsapp_chat', true)->pluck('code')->all());
    }

    public function test_lower_plans_see_the_status_but_every_chat_route_is_closed(): void
    {
        $this->onPlan('growth');
        $this->actingAsMember($this->admin);

        $this->getJson('/api/v1/whatsapp/status')->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('plan', 'Growth')->assertJsonPath('state', 'unavailable');
        $this->getJson('/api/v1/whatsapp/conversations')->assertStatus(402);
        $this->getJson("/api/v1/whatsapp/conversations/{$this->client->id}")->assertStatus(402);
        $this->postJson("/api/v1/whatsapp/conversations/{$this->client->id}/messages", ['body' => 'Hi'])->assertStatus(402);
        $this->postJson('/api/v1/whatsapp/connect')->assertStatus(402);
    }

    public function test_a_workspace_without_a_plan_cannot_use_whatsapp(): void
    {
        $this->actingAsMember($this->admin)->getJson('/api/v1/whatsapp/conversations')->assertStatus(402);
    }

    public function test_the_admin_links_a_phone_by_scanning_a_qr_code_on_their_own_instance(): void
    {
        $this->onPlan('enterprise');
        $instance = 'spiderplms-'.$this->tenant->id;
        Http::fake([
            'evo.test/instance/fetchInstances*' => Http::response([]),
            'evo.test/instance/create' => Http::response(['instance' => ['instanceName' => $instance]]),
            "evo.test/webhook/set/{$instance}" => Http::response(['ok' => true]),
            "evo.test/instance/connectionState/{$instance}" => Http::response(['instance' => ['state' => 'close']]),
            "evo.test/instance/connect/{$instance}" => Http::response(['base64' => 'data:image/png;base64,QR']),
        ]);

        $this->actingAsMember($this->admin)->postJson('/api/v1/whatsapp/connect')
            ->assertOk()->assertJsonPath('state', 'close')->assertJsonPath('qr', 'data:image/png;base64,QR');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/instance/create') && $r['instanceName'] === $instance);
        Http::assertSent(fn ($r) => str_contains($r->url(), "/webhook/set/{$instance}") && str_contains($r['webhook']['url'], 'token=hook-secret'));
    }

    public function test_status_reports_the_linked_number_once_connected(): void
    {
        $this->onPlan('enterprise');
        $instance = 'spiderplms-'.$this->tenant->id;
        Http::fake([
            "evo.test/instance/connectionState/{$instance}" => Http::response(['instance' => ['state' => 'open']]),
            'evo.test/instance/fetchInstances*' => Http::response([['name' => $instance, 'ownerJid' => '919000011111@s.whatsapp.net']]),
        ]);

        $this->actingAsMember($this->admin)->getJson('/api/v1/whatsapp/status')
            ->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('state', 'open')->assertJsonPath('number', '919000011111');
    }

    public function test_only_an_organization_admin_can_link_or_unlink_the_phone(): void
    {
        $this->onPlan('enterprise');
        $developer = $this->makeMember(Role::Developer, $this->tenant);

        $this->actingAsMember($developer)->postJson('/api/v1/whatsapp/connect')->assertForbidden();
        $this->postJson('/api/v1/whatsapp/disconnect')->assertForbidden();
    }

    public function test_conversations_list_unread_counts_and_opening_a_thread_marks_them_read(): void
    {
        $this->onPlan('enterprise');
        $quiet = Client::factory()->forTenant($this->tenant)->create(['name' => 'Zed Corp', 'whatsapp_number' => '911111111111']);
        ClientMessage::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'channel' => 'whatsapp', 'direction' => 'in', 'body' => 'Hello?', 'status' => 'received', 'provider_message_id' => 'A1']);
        ClientMessage::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'channel' => 'whatsapp', 'direction' => 'in', 'body' => 'Any update?', 'status' => 'received', 'provider_message_id' => 'A2']);
        ClientMessage::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'channel' => 'whatsapp', 'direction' => 'out', 'body' => 'A draft', 'status' => 'draft']);

        $this->actingAsMember($this->admin);
        $list = $this->getJson('/api/v1/whatsapp/conversations')->assertOk()->assertJsonPath('unread', 2)->json('data');
        $this->assertSame($this->client->id, $list[0]['client_id']);
        $this->assertSame(2, $list[0]['unread']);
        $this->assertSame('Any update?', $list[0]['last_message']['body']);
        $this->assertSame($quiet->id, $list[1]['client_id']);

        $this->getJson("/api/v1/whatsapp/conversations/{$this->client->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/whatsapp/conversations')->assertJsonPath('unread', 0);
        $this->getJson('/api/v1/whatsapp/conversations?search=zed')->assertJsonCount(1, 'data');
    }

    public function test_a_message_typed_in_the_chat_is_sent_from_the_workspace_instance(): void
    {
        $this->onPlan('enterprise');
        $instance = 'spiderplms-'.$this->tenant->id;
        Http::fake(["evo.test/message/sendText/{$instance}" => Http::response(['key' => ['id' => 'OUT-1']])]);

        $this->actingAsMember($this->admin)->postJson("/api/v1/whatsapp/conversations/{$this->client->id}/messages", ['body' => 'Hello Nora'])
            ->assertCreated()->assertJsonPath('data.status', 'sent')->assertJsonPath('data.direction', 'out');

        Http::assertSent(fn ($r) => $r['number'] === '919876543210' && $r['text'] === 'Hello Nora');
        $this->assertDatabaseHas('client_messages', ['client_id' => $this->client->id, 'body' => 'Hello Nora', 'provider_message_id' => 'OUT-1', 'created_by' => $this->admin->id]);
    }

    public function test_a_client_without_a_number_cannot_be_messaged(): void
    {
        $this->onPlan('enterprise');
        $nobody = Client::factory()->forTenant($this->tenant)->create(['whatsapp_number' => null]);

        $this->actingAsMember($this->admin)->postJson("/api/v1/whatsapp/conversations/{$nobody->id}/messages", ['body' => 'Hi'])->assertStatus(422);
    }

    public function test_conversations_never_cross_workspaces(): void
    {
        $this->onPlan('enterprise');
        $other = Tenant::factory()->create(['plan_id' => Plan::where('code', 'enterprise')->value('id')]);
        $foreign = Client::factory()->forTenant($other)->create(['whatsapp_number' => '919999999999']);

        $this->actingAsMember($this->admin);
        $this->getJson("/api/v1/whatsapp/conversations/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/whatsapp/conversations/{$foreign->id}/messages", ['body' => 'Hi'])->assertNotFound();
        $this->assertNotContains($foreign->id, array_column($this->getJson('/api/v1/whatsapp/conversations')->json('data'), 'client_id'));
    }

    public function test_incoming_messages_land_in_the_workspace_that_owns_the_instance_and_only_on_a_whatsapp_plan(): void
    {
        $other = Tenant::factory()->create(['plan_id' => Plan::where('code', 'enterprise')->value('id')]);
        Client::factory()->forTenant($other)->create(['whatsapp_number' => '919876543210']); // same number, another workspace
        $payload = fn (Tenant $t, string $id) => ['event' => 'messages.upsert', 'instance' => 'spiderplms-'.$t->id, 'data' => [
            'key' => ['remoteJid' => '919876543210@s.whatsapp.net', 'fromMe' => false, 'id' => $id],
            'message' => ['conversation' => 'Hi there'],
        ]];

        $this->onPlan('growth');
        $this->postJson('/api/v1/webhooks/whatsapp?token=hook-secret', $payload($this->tenant, 'W1'))->assertOk();
        $this->assertDatabaseCount('client_messages', 0);

        $this->onPlan('enterprise');
        $this->postJson('/api/v1/webhooks/whatsapp?token=hook-secret', $payload($this->tenant, 'W2'))->assertOk();
        $this->assertDatabaseHas('client_messages', ['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'provider_message_id' => 'W2']);
        $this->assertDatabaseCount('client_messages', 1);
    }

    public function test_drafting_a_whatsapp_message_needs_the_plan(): void
    {
        $this->onPlan('starter');
        $project = $this->projectFor($this->admin, ['client_id' => $this->client->id]);

        $this->actingAsMember($this->admin)->postJson("/api/v1/tracker/projects/{$project->id}/messages/draft", ['channel' => 'whatsapp', 'reason' => 'custom', 'instruction' => 'Hi'])->assertStatus(402);
        $this->postJson("/api/v1/tracker/projects/{$project->id}/messages/draft", ['channel' => 'email', 'reason' => 'custom', 'instruction' => 'Hi'])->assertCreated();
    }
}
