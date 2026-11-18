<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Multi-country / multi-currency platform management, per-country pricing,
 * tenant creation and system settings.
 */
class PlatformAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->super = User::factory()->superAdmin()->create();
    }

    public function test_public_config_lists_active_countries_and_currencies(): void
    {
        Country::where('code', 'JP')->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/public/config')->assertOk()
            ->assertJsonPath('app.default_country', 'US')
            ->assertJsonFragment(['code' => 'IN']);

        $this->assertNotContains('JP', collect($response->json('countries'))->pluck('code')->all());
    }

    public function test_pricing_is_shown_in_the_detected_countrys_currency(): void
    {
        $in = $this->getJson('/api/v1/public/pricing?country=IN')->assertOk()
            ->assertJsonPath('country.code', 'IN')
            ->assertJsonPath('currency.code', 'INR')
            ->assertJsonPath('is_fallback', false);

        // Growth plan: $49 → 49 × 83.5 = 4091.5 → rounded to a tens price in INR
        $growth = collect($in->json('plans'))->firstWhere('code', 'growth');
        $this->assertSame('INR', $growth['currency']);
        $this->assertSame(409000, $growth['price_monthly']);

        $de = $this->getJson('/api/v1/public/pricing?country=de')->assertJsonPath('currency.code', 'EUR');
        $this->assertSame(4500, collect($de->json('plans'))->firstWhere('code', 'growth')['price_monthly']);
    }

    public function test_super_admin_creates_a_plan_with_fixed_country_prices(): void
    {
        $response = $this->actingAs($this->super, 'sanctum')->postJson('/api/v1/admin/plans', [
            'name' => 'Pro Plus', 'price_monthly' => 9900, 'price_yearly' => 99000, 'features' => ['One'],
            'prices' => [['country_code' => 'IN', 'price_monthly' => 799900, 'price_yearly' => 7999000]],
        ])->assertCreated()->assertJsonPath('data.code', 'pro-plus');

        $this->assertCount(1, $response->json('data.prices'));

        $in = collect($this->getJson('/api/v1/public/pricing?country=IN')->json('plans'))->firstWhere('code', 'pro-plus');
        $this->assertSame(799900, $in['price_monthly']);
        $this->assertSame('INR', $in['currency']);

        $de = collect($this->getJson('/api/v1/public/pricing?country=DE')->json('plans'))->firstWhere('code', 'pro-plus');
        $this->assertSame('EUR', $de['currency']);
        $this->assertNotSame(799900, $de['price_monthly']);

        $id = $response->json('data.id');
        $this->actingAs($this->super, 'sanctum')->patchJson("/api/v1/admin/plans/{$id}", ['prices' => []])->assertOk()->assertJsonCount(0, 'data.prices');
        $this->actingAs($this->super, 'sanctum')->deleteJson("/api/v1/admin/plans/{$id}")->assertOk();
    }

    public function test_unknown_or_disabled_countries_fall_back_to_the_platform_default(): void
    {
        Country::where('code', 'FR')->update(['is_active' => false]);

        foreach (['ZZ', 'FR', ''] as $code) {
            $this->getJson('/api/v1/public/pricing?country='.$code)->assertOk()
                ->assertJsonPath('country.code', 'US')
                ->assertJsonPath('currency.code', 'USD')
                ->assertJsonPath('is_fallback', true);
        }
    }

    public function test_super_admin_adds_a_tenant_with_country_currency_and_a_login(): void
    {
        $response = $this->actingAsMember($this->super)->postJson('/api/v1/admin/tenants', [
            'organization' => 'Sakura Systems', 'slug' => 'sakura', 'plan_id' => Plan::where('code', 'growth')->value('id'), 'country_code' => 'JP',
            'status' => 'active', 'name' => 'Aiko Tanaka', 'email' => 'aiko@sakura.test',
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'JP')
            ->assertJsonPath('data.currency', 'JPY')
            ->assertJsonPath('data.status', 'active');

        $response->assertJsonPath('data.url', url('/sakura'));

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => 'sakura', 'email' => 'aiko@sakura.test'])->assertOk();
    }

    public function test_tenant_creation_validates_slug_country_and_uniqueness(): void
    {
        Tenant::factory()->create(['slug' => 'taken']);
        $base = ['organization' => 'X', 'plan_id' => Plan::where('code', 'starter')->value('id'), 'country_code' => 'IN', 'status' => 'trial', 'name' => 'A', 'email' => 'a@x.test'];

        $this->actingAsMember($this->super);
        $this->postJson('/api/v1/admin/tenants', $base + ['slug' => 'taken'])->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/admin/tenants', $base + ['slug' => 'admin'])->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/admin/tenants', ['country_code' => 'XX'] + $base + ['slug' => 'ok-slug'])->assertJsonValidationErrors('country_code');
    }

    public function test_tenant_detail_shows_usage_and_users_and_can_be_edited(): void
    {
        $admin = $this->makeMember(Role::OrgAdmin);
        $this->projectFor($admin);
        $tenant = Tenant::find($admin->tenant_id);

        $this->actingAsMember($this->super)->getJson("/api/v1/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('stats.users', 1)
            ->assertJsonPath('stats.projects', 1)
            ->assertJsonPath('users.0.roles.0', 'org-admin');

        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}", ['status' => 'suspended', 'currency' => 'EUR', 'admin_notes' => 'Late payer'])
            ->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.currency', 'EUR');

        $this->actingAsMember($admin)->getJson('/api/v1/auth/me')->assertStatus(402);
    }

    public function test_tenant_admin_notes_are_hidden_from_tenant_users(): void
    {
        $admin = $this->makeMember(Role::OrgAdmin);
        Tenant::whereKey($admin->tenant_id)->update(['admin_notes' => 'secret']);

        $this->actingAsMember($admin)->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('tenant.admin_notes');
    }

    public function test_tenant_users_cannot_use_platform_endpoints(): void
    {
        $this->actingAsMember($this->makeMember(Role::OrgAdmin));

        foreach (['stats', 'countries', 'currencies', 'settings', 'plans', 'tenants'] as $path) {
            $this->getJson("/api/v1/admin/{$path}")->assertForbidden();
        }
        $this->postJson('/api/v1/admin/tenants', [])->assertForbidden();
    }

    public function test_super_admin_manages_countries_and_currencies(): void
    {
        $this->actingAsMember($this->super);

        $this->postJson('/api/v1/admin/currencies', ['code' => 'qar', 'name' => 'Qatari Riyal', 'symbol' => 'QR', 'rate' => 3.64, 'decimals' => 2])
            ->assertCreated()->assertJsonPath('data.code', 'QAR');
        $this->postJson('/api/v1/admin/countries', ['code' => 'qa', 'name' => 'Qatar', 'currency_code' => 'QAR'])
            ->assertCreated()->assertJsonPath('data.code', 'QA');

        $this->getJson('/api/v1/public/pricing?country=QA')->assertJsonPath('currency.code', 'QAR');

        $currency = Currency::where('code', 'INR')->first();
        $this->patchJson("/api/v1/admin/currencies/{$currency->id}", ['rate' => 90])->assertOk();
        $this->assertSame(90.0, Currency::where('code', 'INR')->value('rate'));

        $this->postJson('/api/v1/admin/countries', ['code' => 'QB', 'name' => 'Nowhere', 'currency_code' => 'XXX'])->assertJsonValidationErrors('currency_code');
    }

    public function test_default_and_base_currency_and_country_are_protected(): void
    {
        $this->actingAsMember($this->super);
        $us = Country::where('code', 'US')->first();
        $usd = Currency::where('code', 'USD')->first();

        $this->patchJson("/api/v1/admin/countries/{$us->id}", ['is_active' => false])->assertStatus(422);
        $this->deleteJson("/api/v1/admin/countries/{$us->id}")->assertStatus(422);
        $this->patchJson("/api/v1/admin/currencies/{$usd->id}", ['rate' => 2])->assertStatus(422);
        $this->deleteJson("/api/v1/admin/currencies/{$usd->id}")->assertStatus(422);

        $in = Country::where('code', 'IN')->first();
        Tenant::factory()->create(['country_code' => 'IN']);
        $this->deleteJson("/api/v1/admin/countries/{$in->id}")->assertStatus(422);
    }

    public function test_system_settings_drive_the_platform(): void
    {
        $this->actingAsMember($this->super)->putJson('/api/v1/admin/settings', [
            'app_name' => 'Acme PLMS', 'default_country' => 'IN', 'default_currency' => 'INR', 'default_theme' => 'dark',
            'registration_open' => false, 'trial_days' => 30, 'maintenance_message' => 'Maintenance tonight',
        ])->assertOk()->assertJsonPath('data.default_country', 'IN');

        $this->getJson('/api/v1/public/config')
            ->assertJsonPath('app.name', 'Acme PLMS')
            ->assertJsonPath('app.default_theme', 'dark')
            ->assertJsonPath('app.registration_open', false)
            ->assertJsonPath('app.maintenance_message', 'Maintenance tonight');

        $this->getJson('/api/v1/public/pricing?country=ZZ')->assertJsonPath('currency.code', 'INR');

        $this->postJson('/api/v1/auth/register/order', [
            'organization' => 'Closed Co', 'plan' => 'growth', 'name' => 'A', 'email' => 'a@closed.test',
        ])->assertForbidden();
    }

    public function test_registration_is_priced_in_the_chosen_countrys_currency(): void
    {
        config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'test_secret']);
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_IN1', 'amount' => 1, 'currency' => 'INR'])]);

        $this->postJson('/api/v1/auth/register/order', [
            'organization' => 'Bharat Tech', 'country_code' => 'in', 'plan' => 'growth', 'name' => 'Asha', 'email' => 'asha@bharat.test',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['currency'] === 'INR' && $request['amount'] > 4900 && $request->hasHeader('Authorization'));
    }

    public function test_platform_stats_summarise_tenants_by_country(): void
    {
        Tenant::factory()->count(2)->create(['country_code' => 'IN', 'plan_id' => Plan::where('code', 'growth')->value('id')]);
        Tenant::factory()->create(['country_code' => 'DE', 'plan_id' => Plan::where('code', 'starter')->value('id')]);

        $this->actingAsMember($this->super)->getJson('/api/v1/admin/stats')->assertOk()
            ->assertJsonPath('totals.tenants', 3)
            ->assertJsonPath('totals.active', 3)
            ->assertJsonPath('totals.mrr_usd_cents', 4900 * 2 + 1900)
            ->assertJsonPath('by_country.0.code', 'IN');
    }

    public function test_workspace_routes_need_a_tenant_for_super_admins(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsMember($this->super);

        $this->getJson('/api/v1/tracker/projects')->assertStatus(409)->assertJsonPath('code', 'tenant_required');
        $this->withHeader('X-Tenant', $tenant->slug)->getJson('/api/v1/tracker/projects')->assertOk();
    }
}
