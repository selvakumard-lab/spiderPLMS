<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The conversation between a project's team and its client, shown on both sides as one thread.
        Schema::create('project_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender', 10);              // team | client
            $table->string('sender_name')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_chat_messages');
    }
};
