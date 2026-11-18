<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\ActivityLog;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a workspace, its first Organization Admin and a subscription in one
 * transaction. Used by self-service sign-up and by Super Admin "Add tenant".
 */
class TenantRegistrationService
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  array{organization: string, slug: string, plan?: string|null, plan_id?: int|null, country_code?: string|null, currency?: string|null, status?: string|null, trial_days?: int|null, contact_email?: string|null, phone?: string|null, name: string, email: string, password?: string|null}  $data
     * @return array{tenant: Tenant, user: User}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $plan = isset($data['plan_id'])
                ? Plan::find($data['plan_id'])
                : (Plan::where('code', $data['plan'] ?? 'starter')->first() ?? Plan::active()->first());

            $country = Country::with('currency')->where('code', $data['country_code'] ?? SystemSetting::get('default_country'))->first();
            $currency = $data['currency'] ?? $country?->currency_code ?? SystemSetting::get('default_currency');

            $status = TenantStatus::tryFrom($data['status'] ?? 'trial') ?? TenantStatus::Trial;
            $trialEndsAt = $status === TenantStatus::Trial
                ? now()->addDays((int) ($data['trial_days'] ?? SystemSetting::get('trial_days')))
                : null;

            $tenant = Tenant::create([
                'name' => $data['organization'],
                'slug' => $data['slug'],
                'plan_id' => $plan?->id,
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'country_code' => $country?->code,
                'currency' => $currency,
                'timezone' => $country?->timezone ?? 'UTC',
                'contact_email' => $data['contact_email'] ?? $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            $this->context->set($tenant);

            if ($plan !== null) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => $status === TenantStatus::Trial ? 'trialing' : 'active',
                    'current_period_start' => now(),
                    'current_period_end' => $trialEndsAt ?? now()->addMonth(),
                ]);
            }

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?? Str::random(40), // sign-in is by e-mail OTP; the password is never used
            ]);

            $user->assignRole(Role::OrgAdmin->value);

            ActivityLog::record('tenant.registered', $tenant, ['plan' => $plan?->code, 'country' => $country?->code], $user->id);

            return ['tenant' => $tenant, 'user' => $user];
        });
    }
}
