<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            $table->string('telegram_username')->nullable()->after('telegram_thread_id');
            $table->string('telegram_first_name')->nullable()->after('telegram_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            $table->dropColumn(['telegram_username', 'telegram_first_name']);
        });
    }
};