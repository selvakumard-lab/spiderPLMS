<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp_number', 20)->nullable(); // digits only, with country code
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'name']);
            $table->index('whatsapp_number');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });

        Schema::create('client_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 10);                       // email, whatsapp
            $table->string('direction', 3)->default('out');      // out, in
            $table->string('reason', 30)->nullable();            // dependency_overdue, status_alert, progress_update, custom
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('to_address')->nullable();
            $table->string('status', 10)->default('draft');      // draft, sent, failed, received
            $table->boolean('ai_generated')->default(false);
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['project_id', 'channel', 'created_at']);
            $table->unique(['channel', 'provider_message_id']);
        });

        Schema::create('chat_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->text('summary');
            $table->string('sentiment', 12)->default('neutral'); // positive, neutral, negative, urgent
            $table->jsonb('pending_items')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->unsignedSmallInteger('messages_considered')->default(0);
            $table->string('source', 10)->default('fallback');   // deepseek, fallback
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_evaluations');
        Schema::dropIfExists('client_messages');
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::dropIfExists('clients');
    }
};
