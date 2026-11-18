<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'used_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('description');
            $table->unsignedInteger('total_working_days')->nullable()->after('due_date');
            $table->date('revised_due_date')->nullable()->after('total_working_days');
            $table->string('tracking_status', 30)->default('on_time')->after('revised_due_date');
            $table->timestamp('status_updated_at')->nullable()->after('tracking_status');
            $table->text('ai_summary')->nullable()->after('status_updated_at');
        });

        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('activity_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('hours_spent', 7, 2)->default(0);
            $table->decimal('days_spent', 6, 2)->default(0);
            $table->decimal('estimated_hours', 7, 2)->nullable();      // AI / manual estimate for this step
            $table->decimal('next_step_estimate_hours', 7, 2)->nullable(); // AI estimate for the following step
            $table->string('status', 20)->default('in_progress');      // pending, in_progress, completed
            $table->unsignedTinyInteger('progress_percent')->default(0); // overall project progress after this step
            $table->text('ai_analysis')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'activity_date']);
        });

        Schema::create('project_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('meeting_date');
            $table->string('title');
            $table->text('details')->nullable();
            $table->text('discussion_points')->nullable();
            $table->jsonb('links')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'meeting_date']);
        });

        Schema::create('meeting_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_meeting_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('submitted_on');
            $table->string('status', 20)->default('pending');          // pending, approved, rejected
            $table->date('decided_on')->nullable();
            $table->unsignedInteger('additional_days')->default(0);
            $table->decimal('additional_hours', 7, 2)->default(0);
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('client_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('requested_on');
            $table->date('expected_on')->nullable();
            $table->date('received_on')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'received_on']);
        });
    }

    public function down(): void
    {
        foreach (['client_dependencies', 'change_requests', 'meeting_attachments', 'project_meetings', 'project_activities'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'total_working_days', 'revised_due_date', 'tracking_status', 'status_updated_at', 'ai_summary']);
        });

        Schema::dropIfExists('login_otps');
    }
};
