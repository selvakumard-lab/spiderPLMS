<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide key/value settings (Super Admin → System Settings).
 * Read through SystemSetting::get() — values are cached and defaults come from DEFAULTS.
 */
#[Fillable(['key', 'value'])]
class SystemSetting extends Model
{
    private const CACHE_KEY = 'plms.system_settings';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'app_name' => 'Spider PLMS',
        'tagline' => 'Always know where every project stands.',
        'support_email' => 'selvakumar.d@spiderindia.net',
        'default_country' => 'US',
        'default_currency' => 'USD',
        'default_theme' => 'system',      // light | dark | system
        'registration_open' => true,
        'trial_days' => 14,
        'maintenance_message' => '',      // non-empty shows a banner to everyone
        'invoice_prefix' => 'INV',
        'default_tax_rate' => 0,
        'max_upload_mb' => 25,
    ];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** @return array<string, mixed> every setting, defaults merged with stored values */
    public static function everything(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::everything()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @param array<string, mixed> $values */
    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
