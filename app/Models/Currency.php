<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'symbol', 'rate', 'decimals', 'is_active', 'sort_order'])]
class Currency extends Model
{
    protected $attributes = [
        'rate' => 1,
        'decimals' => 2,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'decimals' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<Currency> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Convert a USD amount in minor units (cents) into this currency's minor units,
     * rounded to a "nice" price (whole units; tens for high-rate currencies).
     */
    public function convertFromUsdMinor(int $usdMinor): int
    {
        $major = ($usdMinor / 100) * $this->rate;
        $step = $this->rate >= 50 ? 10 : 1;
        $rounded = max($step, (int) (round($major / $step) * $step));

        return (int) ($rounded * (10 ** $this->decimals));
    }
}
