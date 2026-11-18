<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'price_monthly', 'price_yearly', 'currency', 'max_users', 'max_projects', 'storage_gb', 'features', 'is_active', 'sort_order'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<PlanPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /** @param Builder<Plan> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * The monthly price for a country, in minor units of that country's currency.
     *
     * @return array{amount: int, currency: string}
     */
    public function monthlyPriceFor(?string $countryCode): array
    {
        $country = Country::active()->where('code', $countryCode)->first()
            ?? Country::active()->where('code', SystemSetting::get('default_country'))->first();

        $fixed = $country ? PlanPrice::where('plan_id', $this->id)->where('country_code', $country->code)->first() : null;

        if ($fixed !== null) {
            return ['amount' => (int) $fixed->price_monthly, 'currency' => $fixed->currency_code];
        }

        $currency = Currency::active()->where('code', $country?->currency_code)->first()
            ?? Currency::where('code', 'USD')->first();

        return $currency === null
            ? ['amount' => (int) $this->price_monthly, 'currency' => 'USD']
            : ['amount' => $currency->convertFromUsdMinor($this->price_monthly), 'currency' => $currency->code];
    }
}
