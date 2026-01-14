<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            $table->string('telegram_invite_token', 80)->nullable()->unique()->after('telegram_chat_id');
            $table->timestamp('telegram_invite_expires_at')->nullable()->after('telegram_invite_token');
            $table->timestamp('telegram_invited_at')->nullable()->after('telegram_invite_expires_at');
            $table->foreignId('telegram_invited_by')->nullable()->constrained('users')->nullOnDelete()->after('telegram_invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('telegram_invited_by');
            $table->dropColumn(['telegram_invite_token', 'telegram_invite_expires_at', 'telegram_invited_at']);
        });
    }
};
