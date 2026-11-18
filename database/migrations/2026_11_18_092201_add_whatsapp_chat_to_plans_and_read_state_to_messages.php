<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('whatsapp_chat')->default(false)->after('storage_gb');
        });

        Schema::table('client_messages', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->index(['tenant_id', 'client_id', 'channel']);
        });

        // The highest plan is the one that includes the WhatsApp QR-scan chat.
        $top = Plan::query()->orderByDesc('sort_order')->first();
        $top?->forceFill(['whatsapp_chat' => true])->save();
    }

    public function down(): void
    {
        Schema::table('client_messages', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'client_id', 'channel']);
            $table->dropColumn('read_at');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_chat');
        });
    }
};
