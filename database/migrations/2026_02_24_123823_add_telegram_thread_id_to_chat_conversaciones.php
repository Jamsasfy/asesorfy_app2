<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            // 1) Quitar el índice único de telegram_chat_id (ya no es único solo)
            $table->dropUnique('chat_conversaciones_telegram_chat_id_unique');

            // 2) Añadir telegram_thread_id (ID del topic en Telegram)
            $table->unsignedBigInteger('telegram_thread_id')->nullable()->after('telegram_chat_id');

            // 3) Índice único compuesto: un topic por chat de Telegram
            $table->unique(['telegram_chat_id', 'telegram_thread_id'], 'chat_conv_tg_chat_thread_unique');

            // 4) Índice simple en telegram_chat_id para búsquedas rápidas
            $table->index('telegram_chat_id', 'chat_conv_telegram_chat_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            // Revertir en orden inverso
            $table->dropIndex('chat_conv_telegram_chat_id_index');
            $table->dropUnique('chat_conv_tg_chat_thread_unique');
            $table->dropColumn('telegram_thread_id');

            // Restaurar el unique original
            $table->unique('telegram_chat_id', 'chat_conversaciones_telegram_chat_id_unique');
        });
    }
};