<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::camel(preg_replace('/[^A-Za-z0-9]+/', ' ', (string) ($this->input('slug') ?: $this->input('organization')))),
            'email' => Str::lower((string) $this->input('email')),
            'country_code' => $this->filled('country_code') ? Str::upper((string) $this->input('country_code')) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization' => ['required', 'string', 'max:120'],
            'plan' => ['required', 'string', Rule::exists('plans', 'code')->where('is_active', true)],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'alpha_num', Rule::notIn(config('plms.reserved_slugs')), Rule::unique('tenants', 'slug')],
            'country_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.unique' => 'This workspace URL is already taken.',
            'slug.not_in' => 'This workspace URL is reserved.',
        ];
    }
}
