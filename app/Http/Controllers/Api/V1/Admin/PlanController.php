<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Plans are priced in USD minor units (the fallback); a plan can also carry fixed
 * per-country prices in that country's own currency, which win over the rate conversion.
 */
class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Plan::with('prices')->withCount('tenants')->orderBy('sort_order')->get()->map(fn (Plan $p) => $this->present($p))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $code = Str::slug($data['code'] ?? $data['name']);
        abort_if(Plan::where('code', $code)->exists(), 422, 'A plan with this code already exists.');

        $plan = DB::transaction(function () use ($data, $code): Plan {
            $plan = Plan::create(collect($data)->except('prices', 'code')->all() + [
                'code' => $code,
                'currency' => 'USD',
                'sort_order' => ((int) Plan::max('sort_order')) + 1,
            ]);
            $this->syncPrices($plan, $data['prices'] ?? []);

            return $plan;
        });

        return response()->json(['data' => $this->present($plan->load('prices')->loadCount('tenants'))], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $this->validated($request, false);

        DB::transaction(function () use ($plan, $data): void {
            $plan->update(collect($data)->except('prices', 'code')->all());
            if (array_key_exists('prices', $data)) {
                $this->syncPrices($plan, $data['prices']);
            }
        });

        return response()->json(['data' => $this->present($plan->load('prices')->loadCount('tenants'))]);
    }

    /**
     * Saves the whole country-price grid in one go: every plan's fixed prices are replaced by the rows sent.
     * A plan or country with no row has no fixed price and falls back to the converted USD price.
     */
    public function syncAllPrices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prices' => ['present', 'array', 'max:2000'],
            'prices.*.plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'prices.*.country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'prices.*.price_monthly' => ['required', 'integer', 'min:0'],
            'prices.*.price_yearly' => ['required', 'integer', 'min:0'],
        ]);

        $byPlan = collect($data['prices'])->groupBy('plan_id');

        DB::transaction(function () use ($byPlan): void {
            foreach (Plan::all() as $plan) {
                $this->syncPrices($plan, $byPlan->get($plan->id, collect())->unique(fn ($row) => strtoupper($row['country_code']))->values()->all());
            }
        });

        return $this->index();
    }

    public function destroy(Plan $plan): JsonResponse
    {
        abort_if($plan->tenants()->exists(), 422, 'This plan is used by tenants — deactivate it instead.');
        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $sometimes = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$sometimes, 'string', 'max:60'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:200'],
            'price_monthly' => [$sometimes, 'integer', 'min:0'],
            'price_yearly' => [$sometimes, 'integer', 'min:0'],
            'max_users' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_projects' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'storage_gb' => ['sometimes', 'integer', 'min:1'],
            'whatsapp_chat' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'prices' => ['sometimes', 'array'],
            'prices.*.country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'prices.*.price_monthly' => ['required', 'integer', 'min:0'],
            'prices.*.price_yearly' => ['required', 'integer', 'min:0'],
        ]);
    }

    /** @param array<int, array{country_code: string, price_monthly: int, price_yearly: int}> $prices */
    private function syncPrices(Plan $plan, array $prices): void
    {
        $keep = [];
        foreach ($prices as $row) {
            $code = strtoupper($row['country_code']);
            $country = Country::where('code', $code)->firstOrFail();
            $plan->prices()->updateOrCreate(['country_code' => $code], [
                'currency_code' => $country->currency_code,
                'price_monthly' => $row['price_monthly'],
                'price_yearly' => $row['price_yearly'],
            ]);
            $keep[] = $code;
        }
        $plan->prices()->whereNotIn('country_code', $keep)->delete();
    }

    /** @return array<string, mixed> */
    private function present(Plan $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'price_monthly' => $p->price_monthly,
            'price_yearly' => $p->price_yearly,
            'currency' => $p->currency,
            'max_users' => $p->max_users,
            'max_projects' => $p->max_projects,
            'storage_gb' => $p->storage_gb,
            'whatsapp_chat' => (bool) $p->whatsapp_chat,
            'features' => $p->features ?? [],
            'is_active' => $p->is_active,
            'tenants_count' => $p->tenants_count ?? $p->tenants()->count(),
            'prices' => $p->prices->map(fn ($r) => [
                'country_code' => $r->country_code,
                'currency_code' => $r->currency_code,
                'price_monthly' => (int) $r->price_monthly,
                'price_yearly' => (int) $r->price_yearly,
            ])->values(),
        ];
    }
}
