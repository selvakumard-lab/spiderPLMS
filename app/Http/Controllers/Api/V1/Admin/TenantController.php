<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Platform (Super Admin) management of tenants.
 */
class TenantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = Tenant::with('plan')
            ->withCount(['users' => fn ($q) => $q->withoutGlobalScopes(), 'projects' => fn ($q) => $q->withoutGlobalScopes()])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner
                ->whereLike('name', '%'.$request->string('search').'%')
                ->orWhereLike('slug', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('country'), fn ($q) => $q->where('country_code', $request->string('country')))
            ->latest()
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return TenantResource::collection($tenants);
    }

    /**
     * Add a tenant: workspace + its Organization Admin. A random password is
     * generated when none is given and returned once so it can be handed over.
     */
    public function store(Request $request, TenantRegistrationService $registration): JsonResponse
    {
        $data = $request->validate([
            'organization' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash', Rule::notIn(config('plms.reserved_slugs')), Rule::unique('tenants', 'slug')],
            'plan_id' => ['required', 'exists:plans,id'],
            'country_code' => ['required', Rule::exists('countries', 'code')],
            'currency' => ['nullable', Rule::exists('currencies', 'code')],
            'status' => ['required', Rule::in(['trial', 'active'])],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'contact_email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $generated = empty($data['password']);
        $data['password'] = $generated ? Str::password(14, symbols: false) : $data['password'];

        ['tenant' => $tenant, 'user' => $user] = $registration->register($data);
        app(TenantContext::class)->set(null);
        ActivityLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->id, 'event' => 'tenant.created_by_admin', 'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => new TenantResource($tenant->load('plan')),
            'admin' => ['name' => $user->name, 'email' => $user->email],
            'temporary_password' => $generated ? $data['password'] : null,
        ], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $context = app(TenantContext::class);

        $details = $context->runAs($tenant, function (): array {
            return [
                'stats' => [
                    'users' => User::count(),
                    'projects' => Project::count(),
                    'last_activity_at' => ActivityLog::max('created_at'),
                ],
                'users' => UserResource::collection(User::with('roles')->orderBy('name')->get())->resolve(),
                'activity' => ActivityLog::with('user')->latest('created_at')->limit(10)->get()->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'user' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
            ];
        });

        return response()->json(['data' => new TenantResource($tenant->load(['plan', 'subscription'])), ...$details]);
    }

    public function update(Request $request, Tenant $tenant): TenantResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'min:3', 'max:40', 'alpha_dash', Rule::notIn(config('plms.reserved_slugs')), Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'status' => ['sometimes', Rule::enum(TenantStatus::class)],
            'plan_id' => ['sometimes', 'exists:plans,id'],
            'country_code' => ['sometimes', 'nullable', Rule::exists('countries', 'code')],
            'currency' => ['sometimes', Rule::exists('currencies', 'code')],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'extend_trial_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'timezone' => ['sometimes', 'string', 'max:60'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:60'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['extend_trial_days'])) {
            $base = $tenant->trial_ends_at?->isFuture() ? $tenant->trial_ends_at : now();
            $data['trial_ends_at'] = $base->copy()->addDays($data['extend_trial_days']);
            $data['status'] ??= TenantStatus::Trial->value;
            unset($data['extend_trial_days']);
        }

        $tenant->update($data);
        ActivityLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->id, 'event' => 'tenant.updated_by_admin',
            'properties' => ['changes' => array_keys($tenant->getChanges())], 'ip_address' => $request->ip(),
        ]);

        return new TenantResource($tenant->load('plan'));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json(null, 204);
    }
}
