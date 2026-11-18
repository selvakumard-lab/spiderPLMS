<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collaboration (documents, comments, approvals, activity log)
 * and billing (invoices, invoice items, payments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->nullableMorphs('documentable');            // task, requirement, milestone…
            $table->string('name');
            $table->string('disk', 20)->default('local');
            $table->string('path');                             // tenants/{uuid}/projects/{id}/…
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);     // bytes
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('documents')->nullOnDelete(); // previous version
            $table->string('visibility', 20)->default('team');  // team, client, private
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable');                      // project, task, requirement…
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);     // hidden from client portal
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('approvable');                       // requirement, milestone, project stage gate
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');   // pending, approved, rejected, changes_requested
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event', 60);                        // project.created, task.status_changed…
            $table->jsonb('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);                       // INV-2026-0001
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft');     // draft, sent, partially_paid, paid, overdue, void
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->nullableMorphs('billable');                 // milestone or time entry batch
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->default('bank_transfer'); // card, upi, bank_transfer, cash
            $table->string('gateway')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'activity_logs', 'approvals', 'comments', 'documents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
