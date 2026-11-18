<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('price_monthly')->default(0); // minor units of currency_code
            $table->unsignedBigInteger('price_yearly')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
