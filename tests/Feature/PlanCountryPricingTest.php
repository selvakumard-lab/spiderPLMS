<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PlanCountryPricingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->super = User::factory()->superAdmin()->create();
    }

    private function id(string $code): int
    {
        return (int) Plan::where('code', $code)->value('id');
    }

    public function test_the_whole_price_grid_is_saved_for_every_plan_in_one_request(): void
    {
        $this->actingAs($this->super, 'sanctum')->putJson('/api/v1/admin/plans/prices', ['prices' => [
            ['plan_id' => $this->id('starter'), 'country_code' => 'IN', 'price_monthly' => 149900, 'price_yearly' => 1499000],
            ['plan_id' => $this->id('starter'), 'country_code' => 'GB', 'price_monthly' => 1500, 'price_yearly' => 15000],
            ['plan_id' => $this->id('growth'), 'country_code' => 'IN', 'price_monthly' => 399900, 'price_yearly' => 3999000],
        ]])->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(2, PlanPrice::where('plan_id', $this->id('starter'))->count());
        $this->assertSame(1, PlanPrice::where('plan_id', $this->id('growth'))->count());
        $this->assertSame(0, PlanPrice::where('plan_id', $this->id('enterprise'))->count());
        $this->assertDatabaseHas('plan_prices', ['plan_id' => $this->id('starter'), 'country_code' => 'IN', 'price_monthly' => 149900]);
    }

    public function test_rows_left_out_of_the_grid_lose_their_fixed_price_and_fall_back_to_conversion(): void
    {
        $this->actingAs($this->super, 'sanctum')->putJson('/api/v1/admin/plans/prices', ['prices' => [
            ['plan_id' => $this->id('starter'), 'country_code' => 'IN', 'price_monthly' => 149900, 'price_yearly' => 1499000],
            ['plan_id' => $this->id('starter'), 'country_code' => 'GB', 'price_monthly' => 1500, 'price_yearly' => 15000],
        ]])->assertOk();

        $this->putJson('/api/v1/admin/plans/prices', ['prices' => [
            ['plan_id' => $this->id('starter'), 'country_code' => 'GB', 'price_monthly' => 1700, 'price_yearly' => 17000],
        ]])->assertOk();

        $this->assertDatabaseMissing('plan_prices', ['plan_id' => $this->id('starter'), 'country_code' => 'IN']);
        $this->assertDatabaseHas('plan_prices', ['plan_id' => $this->id('starter'), 'country_code' => 'GB', 'price_monthly' => 1700]);

        $this->putJson('/api/v1/admin/plans/prices', ['prices' => []])->assertOk();
        $this->assertSame(0, PlanPrice::count());
    }

    public function test_the_public_pricing_uses_the_fixed_price_from_the_grid(): void
    {
        $this->actingAs($this->super, 'sanctum')->putJson('/api/v1/admin/plans/prices', ['prices' => [
            ['plan_id' => $this->id('starter'), 'country_code' => 'IN', 'price_monthly' => 123400, 'price_yearly' => 1234000],
        ]])->assertOk();

        $starter = collect($this->getJson('/api/v1/public/pricing?country=IN')->json('plans'))->firstWhere('code', 'starter');
        $this->assertSame(123400, $starter['price_monthly']);
    }

    public function test_bad_rows_are_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->super, 'sanctum')->putJson('/api/v1/admin/plans/prices', ['prices' => [
            ['plan_id' => $this->id('starter'), 'country_code' => 'IN', 'price_monthly' => 100, 'price_yearly' => 1000],
            ['plan_id' => 9999, 'country_code' => 'ZZ', 'price_monthly' => -5, 'price_yearly' => 1000],
        ]])->assertJsonValidationErrors(['prices.1.plan_id', 'prices.1.country_code', 'prices.1.price_monthly']);

        $this->assertSame(0, PlanPrice::count());
    }

    public function test_only_super_admins_can_change_prices(): void
    {
        $this->putJson('/api/v1/admin/plans/prices', ['prices' => []])->assertUnauthorized();

        $member = $this->makeMember(Role::OrgAdmin);
        $this->actingAsMember($member)->putJson('/api/v1/admin/plans/prices', ['prices' => []])->assertForbidden();
    }
}
