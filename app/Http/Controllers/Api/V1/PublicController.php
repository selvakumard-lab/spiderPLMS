<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unauthenticated endpoints that power the landing page, sign-up and theming.
 */
class PublicController extends Controller
{
    /**
     * Platform branding/settings plus the active countries and currencies.
     */
    public function config(): JsonResponse
    {
        $settings = SystemSetting::everything();

        return response()->json([
            'app' => [
                'name' => $settings['app_name'],
                'tagline' => $settings['tagline'],
                'support_email' => $settings['support_email'],
                'default_theme' => $settings['default_theme'],
                'registration_open' => (bool) $settings['registration_open'],
                'trial_days' => (int) $settings['trial_days'],
                'maintenance_message' => $settings['maintenance_message'],
                'default_country' => $settings['default_country'],
                'default_currency' => $settings['default_currency'],
            ],
            'countries' => Country::active()->get(['code', 'name', 'currency_code', 'dial_code']),
            'currencies' => Currency::active()->get(['code', 'name', 'symbol', 'rate', 'decimals']),
        ]);
    }

    /**
     * Pricing for one country. The browser detects the visitor's country
     * (api.country.is) and passes it here; unknown or disabled countries fall
     * back to the platform default so the page always shows a valid price list.
     */
    public function pricing(Request $request): JsonResponse
    {
        $requested = strtoupper((string) $request->query('country', ''));
        $country = Country::active()->where('code', $requested)->first();
        $isFallback = $country === null;
        $country ??= Country::active()->where('code', SystemSetting::get('default_country'))->first() ?? Country::active()->first();

        $currency = Currency::active()->where('code', $country?->currency_code)->first()
            ?? Currency::active()->where('code', SystemSetting::get('default_currency'))->first()
            ?? Currency::query()->where('code', 'USD')->first();

        $overrides = $country ? PlanPrice::query()->where('country_code', $country->code)->get()->keyBy('plan_id') : collect();

        $plans = Plan::active()->get()->map(function (Plan $plan) use ($currency, $overrides): array {
            $fixed = $overrides->get($plan->id);

            return [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => $fixed ? (int) $fixed->price_monthly : ($currency ? $currency->convertFromUsdMinor($plan->price_monthly) : $plan->price_monthly),
                'price_yearly' => $fixed ? (int) $fixed->price_yearly : ($currency ? $currency->convertFromUsdMinor($plan->price_yearly) : $plan->price_yearly),
                'currency' => $fixed?->currency_code ?? $currency?->code ?? 'USD',
                'max_users' => $plan->max_users,
                'max_projects' => $plan->max_projects,
                'storage_gb' => $plan->storage_gb,
                'features' => $plan->features ?? [],
            ];
        });

        return response()->json([
            'requested' => $requested ?: null,
            'is_fallback' => $isFallback,
            'country' => $country ? ['code' => $country->code, 'name' => $country->name] : null,
            'currency' => $currency ? ['code' => $currency->code, 'symbol' => $currency->symbol, 'decimals' => $currency->decimals] : null,
            'plans' => $plans,
        ]);
    }
}
