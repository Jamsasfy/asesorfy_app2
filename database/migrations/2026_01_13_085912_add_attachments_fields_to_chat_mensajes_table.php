<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    Schema::table('chat_mensajes', function (Blueprint $table) {

        if (! Schema::hasColumn('chat_mensajes', 'file_path')) {
            $table->string('file_path')->nullable()->after('contenido');
        }

        if (! Schema::hasColumn('chat_mensajes', 'file_original_name')) {
            $table->string('file_original_name')->nullable()->after('file_path');
        }

        if (! Schema::hasColumn('chat_mensajes', 'file_mime')) {
            $table->string('file_mime')->nullable()->after('file_original_name');
        }

        if (! Schema::hasColumn('chat_mensajes', 'file_size')) {
            $table->unsignedBigInteger('file_size')->nullable()->after('file_mime');
        }

        if (! Schema::hasColumn('chat_mensajes', 'caption')) {
            $table->text('caption')->nullable()->after('file_size');
        }

        // Telegram refs (solo si no existen)
        if (! Schema::hasColumn('chat_mensajes', 'telegram_file_id')) {
            $table->string('telegram_file_id')->nullable()->after('telegram_message_id');
        }

        if (! Schema::hasColumn('chat_mensajes', 'telegram_file_unique_id')) {
            $table->string('telegram_file_unique_id')->nullable()->after('telegram_file_id');
        }
    });
}


    public function down(): void
{
    Schema::table('chat_mensajes', function (Blueprint $table) {
        $cols = [
            'file_path',
            'file_original_name',
            'file_mime',
            'file_size',
            'caption',
            'telegram_file_id',
            'telegram_file_unique_id',
        ];

        foreach ($cols as $col) {
            if (Schema::hasColumn('chat_mensajes', $col)) {
                $table->dropColumn($col);
            }
        }
    });
}

};
