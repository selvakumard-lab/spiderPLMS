<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Currency::orderBy('sort_order')->orderBy('code')->get()->map(fn (Currency $c) => $c->toArray() + [
            'is_default' => $c->code === SystemSetting::get('default_currency'),
            'countries_count' => Country::where('currency_code', $c->code)->count(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'alpha', 'size:3', Rule::unique('currencies', 'code')],
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['required', 'string', 'max:8'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'decimals' => ['required', 'integer', 'between:0,4'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(['data' => Currency::create($data)], 201);
    }

    public function update(Request $request, Currency $currency): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'symbol' => ['sometimes', 'string', 'max:8'],
            'rate' => ['sometimes', 'numeric', 'gt:0'],
            'decimals' => ['sometimes', 'integer', 'between:0,4'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['is_active'] ?? true) === false && $currency->code === SystemSetting::get('default_currency')) {
            abort(422, 'The default currency cannot be disabled.');
        }
        if ($currency->code === 'USD' && isset($data['rate']) && (float) $data['rate'] !== 1.0) {
            abort(422, 'USD is the base currency; its rate is always 1.');
        }

        $currency->update($data);

        return response()->json(['data' => $currency]);
    }

    public function destroy(Currency $currency): JsonResponse
    {
        abort_if($currency->code === 'USD' || $currency->code === SystemSetting::get('default_currency'), 422, 'The base/default currency cannot be deleted.');
        abort_if(Country::where('currency_code', $currency->code)->exists() || Tenant::where('currency', $currency->code)->exists(), 422, 'This currency is in use. Disable it instead.');

        $currency->delete();

        return response()->json(null, 204);
    }
}
