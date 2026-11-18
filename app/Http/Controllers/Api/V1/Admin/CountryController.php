<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantCounts = Tenant::query()->selectRaw('country_code, count(*) as total')->groupBy('country_code')->pluck('total', 'country_code');

        return response()->json(['data' => Country::orderBy('name')->get()->map(fn (Country $c) => $c->toArray() + [
            'tenants_count' => (int) ($tenantCounts[$c->code] ?? 0),
            'is_default' => $c->code === SystemSetting::get('default_country'),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'alpha', 'size:2', Rule::unique('countries', 'code')],
            'name' => ['required', 'string', 'max:80'],
            'currency_code' => ['required', Rule::exists('currencies', 'code')],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(['data' => Country::create($data)], 201);
    }

    public function update(Request $request, Country $country): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'currency_code' => ['sometimes', Rule::exists('currencies', 'code')],
            'dial_code' => ['nullable', 'string', 'max:8'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['is_active'] ?? true) === false && $country->code === SystemSetting::get('default_country')) {
            abort(422, 'The default country cannot be disabled. Choose another default in System Settings first.');
        }

        $country->update($data);

        return response()->json(['data' => $country]);
    }

    public function destroy(Country $country): JsonResponse
    {
        abort_if($country->code === SystemSetting::get('default_country'), 422, 'The default country cannot be deleted.');
        abort_if(Tenant::where('country_code', $country->code)->exists(), 422, 'Tenants use this country. Disable it instead of deleting.');

        $country->delete();

        return response()->json(null, 204);
    }
}
