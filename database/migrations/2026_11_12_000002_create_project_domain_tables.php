<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project lifecycle domain: clients, projects, members, requirements,
 * milestones, tasks and time entries. Every table carries tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // portal login
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);                        // PRJ-0001
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 30)->default('initiation'); // App\Enums\ProjectStage
            $table->string('status', 20)->default('active');    // active, on_hold, completed, cancelled
            $table->string('priority', 10)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('billing_type', 20)->default('fixed'); // fixed, hourly, non_billable
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('color', 9)->default('#4F46E5');
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_role', 30)->default('developer'); // team_leader, developer, qa, client
            $table->unsignedTinyInteger('allocation')->default(100);  // % of capacity
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 30)->nullable();       // REQ-001
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('functional');  // functional, non_functional, constraint
            $table->string('priority', 10)->default('should');  // MoSCoW: must, should, could, wont
            $table->string('status', 20)->default('draft');     // draft, in_review, approved, rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');   // pending, in_progress, in_review, approved, completed
            $table->decimal('amount', 14, 2)->nullable();       // billable amount for milestone invoicing
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->unsignedInteger('number');                  // per-project sequence: PRJ-0001-42
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('task');        // task, bug, feature, improvement, test
            $table->string('status', 20)->default('todo');      // App\Enums\TaskStatus
            $table->string('priority', 10)->default('medium');  // low, medium, high, urgent
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->unsignedInteger('position')->default(0);    // ordering inside a Kanban column
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assignee_id']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();          // null = timer running
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->boolean('is_billable')->default(true);
            $table->string('approval_status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('invoice_id')->nullable();         // set when billed
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        foreach (['time_entries', 'tasks', 'milestones', 'requirements', 'project_members', 'projects', 'clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
