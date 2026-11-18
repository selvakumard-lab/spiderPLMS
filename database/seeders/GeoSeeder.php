<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Starter countries and currencies. Exchange rates are PLACEHOLDERS (units per 1 USD):
 * Super Admins maintain real rates in Platform → Currencies.
 */
class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['USD', 'US Dollar', '$', 1, 2],
            ['INR', 'Indian Rupee', '₹', 83.5, 2],
            ['EUR', 'Euro', '€', 0.92, 2],
            ['GBP', 'British Pound', '£', 0.79, 2],
            ['AED', 'UAE Dirham', 'د.إ', 3.6725, 2],
            ['AUD', 'Australian Dollar', 'A$', 1.52, 2],
            ['CAD', 'Canadian Dollar', 'C$', 1.36, 2],
            ['SGD', 'Singapore Dollar', 'S$', 1.34, 2],
            ['JPY', 'Japanese Yen', '¥', 150, 0],
            ['SAR', 'Saudi Riyal', '﷼', 3.75, 2],
            ['MYR', 'Malaysian Ringgit', 'RM', 4.7, 2],
            ['ZAR', 'South African Rand', 'R', 18.5, 2],
            ['BRL', 'Brazilian Real', 'R$', 5.0, 2],
            ['NZD', 'New Zealand Dollar', 'NZ$', 1.65, 2],
            ['CHF', 'Swiss Franc', 'CHF', 0.88, 2],
        ];

        foreach ($currencies as $i => [$code, $name, $symbol, $rate, $decimals]) {
            Currency::updateOrCreate(['code' => $code], compact('name', 'symbol', 'rate', 'decimals') + ['is_active' => true, 'sort_order' => $i]);
        }

        $countries = [
            ['US', 'United States', 'USD', '+1', 'America/New_York'],
            ['IN', 'India', 'INR', '+91', 'Asia/Kolkata'],
            ['GB', 'United Kingdom', 'GBP', '+44', 'Europe/London'],
            ['DE', 'Germany', 'EUR', '+49', 'Europe/Berlin'],
            ['FR', 'France', 'EUR', '+33', 'Europe/Paris'],
            ['NL', 'Netherlands', 'EUR', '+31', 'Europe/Amsterdam'],
            ['ES', 'Spain', 'EUR', '+34', 'Europe/Madrid'],
            ['IT', 'Italy', 'EUR', '+39', 'Europe/Rome'],
            ['IE', 'Ireland', 'EUR', '+353', 'Europe/Dublin'],
            ['AE', 'United Arab Emirates', 'AED', '+971', 'Asia/Dubai'],
            ['SA', 'Saudi Arabia', 'SAR', '+966', 'Asia/Riyadh'],
            ['AU', 'Australia', 'AUD', '+61', 'Australia/Sydney'],
            ['NZ', 'New Zealand', 'NZD', '+64', 'Pacific/Auckland'],
            ['CA', 'Canada', 'CAD', '+1', 'America/Toronto'],
            ['SG', 'Singapore', 'SGD', '+65', 'Asia/Singapore'],
            ['MY', 'Malaysia', 'MYR', '+60', 'Asia/Kuala_Lumpur'],
            ['JP', 'Japan', 'JPY', '+81', 'Asia/Tokyo'],
            ['ZA', 'South Africa', 'ZAR', '+27', 'Africa/Johannesburg'],
            ['BR', 'Brazil', 'BRL', '+55', 'America/Sao_Paulo'],
            ['CH', 'Switzerland', 'CHF', '+41', 'Europe/Zurich'],
        ];

        foreach ($countries as [$code, $name, $currency, $dial, $tz]) {
            Country::updateOrCreate(['code' => $code], ['name' => $name, 'currency_code' => $currency, 'dial_code' => $dial, 'timezone' => $tz, 'is_active' => true]);
        }
    }
}
