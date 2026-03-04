<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_mensajes', function (Blueprint $table) {
            $table->index(['chat_id', 'origen', 'created_at'], 'chat_msg_chat_origen_created_idx');
            $table->index(['origen', 'created_at'], 'chat_msg_origen_created_idx'); // opcional pero recomendable
        });
    }

    public function down(): void
    {
        Schema::table('chat_mensajes', function (Blueprint $table) {
            $table->dropIndex('chat_msg_chat_origen_created_idx');
            $table->dropIndex('chat_msg_origen_created_idx');
        });
    }
};
