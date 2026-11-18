<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\LoginCodeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_an_organisation_registers_a_workspace_only_after_a_verified_razorpay_payment(): void
    {
        $order = $this->startPaidRegistration([
            'organization' => 'Globex Labs',
            'plan' => 'growth',
            'name' => 'Grace Hopper',
            'email' => 'Grace@Globex.test',
        ]);
        $this->assertSame('order_TEST1', $order['order_id']);
        $this->assertSame('rzp_test_key', $order['key']);
        $this->assertDatabaseMissing('tenants', ['slug' => 'globexLabs']);

        $this->postJson('/api/v1/auth/register/verify', [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => 'forged',
        ])->assertJsonValidationErrors('payment');
        $this->assertDatabaseMissing('tenants', ['slug' => 'globexLabs']);

        $this->postJson('/api/v1/auth/register/verify', [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => $this->signature('order_TEST1', 'pay_X'),
        ])->assertCreated()
            ->assertJsonPath('tenant.slug', 'globexLabs')
            ->assertJsonPath('tenant.url', url('/globexLabs'))
            ->assertJsonPath('tenant.status', 'active')
            ->assertJsonPath('tenant.plan.code', 'growth')
            ->assertJsonPath('user.email', 'grace@globex.test')
            ->assertJsonStructure(['token', 'permissions']);

        $tenant = Tenant::where('slug', 'globexLabs')->firstOrFail();
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'status' => 'active', 'gateway' => 'razorpay', 'gateway_subscription_id' => 'pay_X']);

        $admin = User::withoutGlobalScopes()->where('email', 'grace@globex.test')->firstOrFail();
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($admin->hasRole(Role::OrgAdmin->value));

        // The same paid order cannot create a second workspace.
        $this->postJson('/api/v1/auth/register/verify', [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => $this->signature('order_TEST1', 'pay_X'),
        ])->assertJsonValidationErrors('payment');
    }

    public function test_reserved_and_duplicate_workspace_slugs_are_rejected(): void
    {
        Tenant::factory()->create(['slug' => 'taken']);

        $payload = ['organization' => 'X', 'name' => 'A', 'email' => 'a@x.test', 'plan' => 'growth'];

        $this->postJson('/api/v1/auth/register/order', $payload + ['slug' => 'admin'])->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/auth/register/order', $payload + ['slug' => 'superAdmin'])->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/auth/register/order', $payload + ['slug' => 'taken'])->assertJsonValidationErrors('slug');
    }

    public function test_a_member_logs_in_with_an_email_otp(): void
    {
        $user = $this->makeMember(Role::Developer);
        $slug = Tenant::find($user->tenant_id)->slug;
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $slug, 'email' => $user->email])->assertOk();
        $code = $this->sentCodeFor($user->email);

        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $slug, 'email' => $user->email, 'code' => $code === '000000' ? '111111' : '000000'])
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $slug, 'email' => $user->email, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('tenant.slug', $slug)
            ->assertJsonStructure(['token']);

        // A code can only be used once.
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $slug, 'email' => $user->email, 'code' => $code])
            ->assertJsonValidationErrors('code');
    }

    public function test_password_login_no_longer_exists(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'x@y.test', 'password' => 'password'])->assertStatus(404);
    }

    public function test_wrong_sign_in_details_get_a_clear_validation_message(): void
    {
        $tenant = Tenant::factory()->create();
        $disabled = $this->makeMember(Role::Developer, $tenant);
        $disabled->forceFill(['status' => 'disabled'])->save();
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $tenant->slug])->assertJsonValidationErrors(['email' => 'Enter your email address.']);
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $tenant->slug, 'email' => 'not-an-email'])->assertJsonValidationErrors(['email' => 'valid email']);
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => 'noSuchPlace', 'email' => 'a@b.test'])->assertJsonValidationErrors(['workspace' => 'find that workspace']);
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $tenant->slug, 'email' => 'nobody@nowhere.test'])->assertJsonValidationErrors(['email' => 'No account uses this email']);
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $tenant->slug, 'email' => $disabled->email])->assertJsonValidationErrors(['email' => 'disabled']);
        Mail::assertNothingSent();

        $member = $this->makeMember(Role::Developer, $tenant);
        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $tenant->slug, 'email' => $member->email])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $tenant->slug, 'email' => $member->email, 'code' => '12'])->assertJsonValidationErrors(['code' => '6 digits']);
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $tenant->slug, 'email' => $member->email, 'code' => 'abcdef'])->assertJsonValidationErrors(['code' => '6 digits']);
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $tenant->slug, 'email' => $member->email, 'code' => '000000'])->assertJsonValidationErrors(['code' => 'attempts left']);
    }

    public function test_the_temporary_static_code_signs_everyone_in_without_sending_email(): void
    {
        config(['plms.static_otp' => '123456']);
        $user = $this->makeMember(Role::Developer);
        $slug = Tenant::find($user->tenant_id)->slug;
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $slug, 'email' => $user->email])->assertOk();
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $slug, 'email' => $user->email, 'code' => '654321'])->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $slug, 'email' => $user->email, 'code' => '123456'])
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_the_same_email_cannot_use_an_otp_for_a_different_workspace(): void
    {
        $user = $this->makeMember(Role::Developer);
        $other = Tenant::factory()->create();

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => $other->slug, 'email' => $user->email])->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/otp/verify', ['workspace' => $other->slug, 'email' => $user->email, 'code' => '123456'])
            ->assertJsonValidationErrors('email');
    }

    public function test_super_admin_signs_in_with_an_email_otp_and_no_workspace(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'root@plms.test']);
        $member = $this->makeMember(Role::OrgAdmin);
        Mail::fake();

        // Only the platform admin can use the workspace-less sign-in.
        $this->postJson('/api/v1/auth/otp/request', ['email' => $member->email])->assertJsonValidationErrors('email');
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/otp/request', ['email' => $admin->email])->assertOk();
        $code = $this->sentCodeFor($admin->email);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => $code === '000000' ? '111111' : '000000'])
            ->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/otp/verify', ['email' => $admin->email, 'code' => $code])
            ->assertOk()->assertJsonPath('user.is_super_admin', true)->assertJsonPath('tenant', null);
    }

    public function test_the_sign_in_email_is_branded_and_carries_the_code(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'acmeDigital', 'name' => 'Acme Digital']);
        $user = $this->makeMember(Role::OrgAdmin, $tenant);
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', ['workspace' => 'acmeDigital', 'email' => $user->email])->assertOk();

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($user): bool {
            $mail->assertHasSubject("{$mail->code} is your {$mail->appName} sign-in code");
            $mail->assertSeeInHtml($mail->code);
            $mail->assertSeeInHtml('Acme Digital');
            $mail->assertSeeInHtml(url('/acmeDigital'));
            $mail->assertSeeInText($mail->code);
            $mail->assertSeeInText('expires in 10 minutes');

            return $mail->hasTo($user->email) && preg_match('/^\d{6}$/', $mail->code) === 1;
        });
    }

    public function test_the_profile_updates_details_but_there_is_no_password_to_change(): void
    {
        $user = $this->makeMember(Role::Developer);
        $originalHash = $user->password;

        $this->actingAsMember($user)->patchJson('/api/v1/auth/profile', [
            'name' => 'New Name', 'job_title' => 'QA', 'phone' => '+91 90000 00000',
            'password' => 'NewPassw0rd!', 'password_confirmation' => 'NewPassw0rd!', 'current_password' => 'x',
        ])->assertOk()->assertJsonPath('user.name', 'New Name')->assertJsonPath('user.job_title', 'QA');

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->patchJson('/api/v1/auth/profile', ['name' => ''])->assertJsonValidationErrors('name');
    }

    public function test_a_user_can_sign_out_every_other_device_but_stays_signed_in_here(): void
    {
        $user = $this->makeMember(Role::OrgAdmin);
        $here = $user->createToken('this-device')->plainTextToken;
        $user->createToken('phone');
        $user->createToken('laptop');
        $other = $this->makeMember(Role::Developer, Tenant::find($user->tenant_id));
        $other->createToken('theirs');

        $this->withToken($here)->postJson('/api/v1/auth/sessions/revoke-others')->assertOk()->assertJsonPath('revoked', 2);

        $this->assertSame(['this-device'], $user->tokens()->pluck('name')->all());
        $this->assertSame(1, $other->tokens()->count());
        $this->withToken($here)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_workspace_slugs_are_matched_case_insensitively(): void
    {
        Tenant::factory()->create(['slug' => 'acmeDigital', 'name' => 'Acme Digital']);

        $this->getJson('/api/v1/public/workspaces/acmedigital')->assertOk()->assertJsonPath('slug', 'acmeDigital');
        $this->getJson('/api/v1/public/workspaces/acmeDigital')->assertOk();
    }

    public function test_members_of_a_suspended_workspace_are_blocked(): void
    {
        $user = $this->makeMember(Role::OrgAdmin, Tenant::factory()->suspended()->create());

        $this->actingAsMember($user)->getJson('/api/v1/auth/me')
            ->assertStatus(402)
            ->assertJsonPath('code', 'tenant_inactive');
    }

    public function test_me_returns_profile_tenant_and_permissions(): void
    {
        $user = $this->makeMember(Role::ProjectManager);

        $this->actingAsMember($user)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.roles.0', 'project-manager')
            ->assertJsonPath('tenant.id', $user->tenant_id)
            ->assertJsonFragment(['projects.create']);
    }

    /**
     * Fakes Razorpay's order API, then returns the order the registration endpoint produced.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function startPaidRegistration(array $payload): array
    {
        config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'test_secret']);
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_TEST1', 'amount' => 4900, 'currency' => 'USD'])]);

        return $this->postJson('/api/v1/auth/register/order', $payload)->assertOk()->json();
    }

    private function sentCodeFor(string $email): string
    {
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($email, &$code): bool {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        return (string) $code;
    }

    private function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, 'test_secret');
    }
}
