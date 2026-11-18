<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Platform overview for the Super Admin home screen.
 */
class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $byStatus = Tenant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $countries = Country::pluck('name', 'code');

        $mrrUsdCents = Tenant::with('plan')->where('status', TenantStatus::Active->value)->get()
            ->sum(fn (Tenant $t) => $t->plan?->price_monthly ?? 0);

        $byCountry = Tenant::query()->selectRaw('country_code, count(*) as total')->groupBy('country_code')->orderByDesc('total')->get()
            ->map(fn ($row) => ['code' => $row->country_code, 'name' => $countries[$row->country_code] ?? 'Unknown', 'tenants' => (int) $row->total]);

        $byPlan = Plan::withCount('tenants')->orderBy('sort_order')->get()
            ->map(fn (Plan $p) => ['code' => $p->code, 'name' => $p->name, 'tenants' => $p->tenants_count]);

        $monthly = collect(range(5, 0))->map(function (int $ago): array {
            $start = now()->subMonths($ago)->startOfMonth();

            return ['month' => $start->format('M Y'), 'tenants' => Tenant::whereBetween('created_at', [$start, $start->copy()->endOfMonth()])->count()];
        })->values();

        return response()->json([
            'totals' => [
                'tenants' => Tenant::count(),
                'active' => (int) ($byStatus['active'] ?? 0),
                'trial' => (int) ($byStatus['trial'] ?? 0),
                'suspended' => (int) (($byStatus['suspended'] ?? 0) + ($byStatus['cancelled'] ?? 0)),
                'users' => User::withoutGlobalScopes()->whereNotNull('tenant_id')->count(),
                'projects' => Project::withoutGlobalScopes()->count(),
                'mrr_usd_cents' => (int) $mrrUsdCents,
            ],
            'by_country' => $byCountry,
            'by_plan' => $byPlan,
            'new_tenants' => $monthly,
        ]);
    }
}
