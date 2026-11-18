<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS core: subscription plans, tenants (organisations / workspaces),
 * subscriptions and the tenant columns on users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // starter, growth, enterprise
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_monthly')->default(0); // minor units (cents / paise)
            $table->unsignedInteger('price_yearly')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('max_users')->nullable();    // null = unlimited
            $table->unsignedInteger('max_projects')->nullable();
            $table->unsignedInteger('storage_gb')->default(5);
            $table->jsonb('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();                  // workspace sub-domain: {slug}.plms.app
            $table->string('custom_domain')->nullable()->unique();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('trial');    // trial, active, past_due, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->jsonb('settings')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('billing_cycle', 10)->default('monthly'); // monthly, yearly
            $table->string('status', 20)->default('active');
            $table->string('gateway')->nullable();               // stripe, razorpay
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->string('job_title')->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('avatar_path')->nullable();
            $table->string('status', 20)->default('active');   // active, invited, disabled
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        // E-mail is unique per tenant, not globally.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropIndex(['email']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['is_super_admin', 'job_title', 'phone', 'avatar_path', 'status', 'hourly_rate', 'last_login_at', 'deleted_at']);
            $table->unique('email');
        });
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
