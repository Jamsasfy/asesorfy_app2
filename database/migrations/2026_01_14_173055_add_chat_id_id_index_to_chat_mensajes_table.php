<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_mensajes', function (Blueprint $table) {
            // Optimiza: último mensaje por chat (ORDER BY id DESC) y paginado por id
            $table->index(['chat_id', 'id'], 'chat_mensajes_chat_id_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('chat_mensajes', function (Blueprint $table) {
            $table->dropIndex('chat_mensajes_chat_id_id_index');
        });
    }
};
