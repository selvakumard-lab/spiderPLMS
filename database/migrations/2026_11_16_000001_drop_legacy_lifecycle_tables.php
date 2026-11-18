<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the original 8-stage lifecycle schema (tasks, time, invoices, documents, ...).
 * Only the simplified project tracker remains.
 */
return new class extends Migration
{
    /** Child tables first so foreign keys never block a drop. */
    private const LEGACY_TABLES = [
        'invoice_items', 'payments', 'invoices', 'approvals', 'comments', 'documents', 'time_entries',
        'tasks', 'requirements', 'milestones', 'project_members', 'notifications',
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['tenant_id', 'stage']);
            $table->dropColumn(['client_id', 'manager_id', 'stage', 'priority', 'closed_at', 'budget', 'billing_type', 'color']);
        });

        foreach (self::LEGACY_TABLES as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('clients');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_path', 'hourly_rate']);
        });
    }

    /**
     * The lifecycle module was removed for good; restore it from the earlier migrations or a backup.
     */
    public function down(): void
    {
        //
    }
};
