<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level reference data managed by Super Admins:
 * currencies, countries, global system settings, plus tenant contact/country fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();               // ISO 4217
            $table->string('name');
            $table->string('symbol', 8);
            $table->decimal('rate', 18, 6)->default(1);         // units of this currency per 1 USD (base)
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();                // ISO 3166-1 alpha-2
            $table->string('name');
            $table->string('currency_code', 3);                 // default currency of the country
            $table->string('dial_code', 8)->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('currency_code');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('currency');
            $table->string('contact_email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id', 60)->nullable();
            $table->text('admin_notes')->nullable();           // visible to super admins only
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropColumn(['country_code', 'contact_email', 'phone', 'address', 'tax_id', 'admin_notes']);
        });
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
