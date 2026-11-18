<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A portal user is an ordinary user row that belongs to one client record.
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('visible_to_client')->default(true);
            $table->timestamps();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
