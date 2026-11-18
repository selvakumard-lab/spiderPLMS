<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => SystemSetting::everything()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_name' => ['sometimes', 'string', 'max:60'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'support_email' => ['sometimes', 'email'],
            'default_country' => ['sometimes', Rule::exists('countries', 'code')->where('is_active', true)],
            'default_currency' => ['sometimes', Rule::exists('currencies', 'code')->where('is_active', true)],
            'default_theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'registration_open' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'integer', 'between:1,365'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:300'],
            'invoice_prefix' => ['sometimes', 'string', 'max:10', 'alpha_dash'],
            'default_tax_rate' => ['sometimes', 'numeric', 'between:0,100'],
            'max_upload_mb' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        SystemSetting::put($data);

        return response()->json(['data' => SystemSetting::everything()]);
    }
}
