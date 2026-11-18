<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The event ledger: every timeline line now says what happened (a machine-readable type), when it really
        // happened, to which record, and with which old and new values. Existing lines keep working unchanged.
        Schema::table('project_events', function (Blueprint $table): void {
            $table->string('event_type', 40)->nullable()->after('kind');
            $table->timestamp('occurred_at')->nullable()->after('body');
            $table->string('subject_type', 30)->nullable()->after('occurred_at');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->jsonb('payload')->nullable()->after('subject_id');
            $table->boolean('visible_to_client')->default(true)->after('payload');
            $table->index(['project_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });
        DB::table('project_events')->whereNull('occurred_at')->update(['occurred_at' => DB::raw('created_at')]);

        // Plan steps get an owner, an estimate in working days, expected deliverables and exact start/finish times.
        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('stage')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('estimated_days')->nullable()->after('due_date');
            $table->jsonb('deliverables')->nullable()->after('description');
            $table->string('source', 10)->default('manual')->after('status'); // manual, default, ai
            $table->timestamp('started_at')->nullable()->after('completed_on');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        // What the AI read from an uploaded document, before and after it becomes a project.
        Schema::create('project_ai_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('text_length')->default(0);
            $table->string('source', 10)->default('fallback');   // deepseek, fallback
            $table->unsignedTinyInteger('confidence')->default(0); // 0-100
            $table->jsonb('extracted');                            // name, type, scope, modules, deliverables, technologies...
            $table->jsonb('estimate');                             // working days, complexity, assumptions, risks, steps
            $table->timestamps();
            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ai_analyses');

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['estimated_days', 'deliverables', 'source', 'started_at', 'completed_at']);
        });

        Schema::table('project_events', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'occurred_at']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['event_type', 'occurred_at', 'subject_type', 'subject_id', 'payload', 'visible_to_client']);
        });
    }
};
