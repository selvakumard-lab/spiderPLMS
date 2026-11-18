<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The project plan: one row per step from kickoff to delivery, with its own deadline and actual dates.
        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30)->nullable();       // one of Project::STAGES, when the step belongs to a stage
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('planned_start')->nullable();
            $table->date('due_date')->nullable();
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed
            $table->timestamps();
            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
