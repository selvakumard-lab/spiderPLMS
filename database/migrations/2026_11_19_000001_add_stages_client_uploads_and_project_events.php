<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('stage', 30)->default('requirements')->after('status');
        });

        Schema::table('project_documents', function (Blueprint $table): void {
            $table->boolean('from_client')->default(false)->after('visible_to_client');
        });

        // Everything either side does on a project, shown on both sides as one timeline.
        Schema::create('project_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor', 10);              // team | client
            $table->string('actor_name')->nullable();
            $table->string('kind', 30);
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_events');
        Schema::table('project_documents', fn (Blueprint $table) => $table->dropColumn('from_client'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('stage'));
    }
};
