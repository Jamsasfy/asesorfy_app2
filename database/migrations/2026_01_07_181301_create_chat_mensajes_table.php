<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chat_id')->constrained('chat_conversaciones')->cascadeOnDelete();

            $table->string('origen'); // cliente|asesor|sistema
            $table->string('tipo')->default('text'); // text|photo|document|voice|...

            $table->text('contenido')->nullable();

            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->unsignedBigInteger('telegram_update_id')->nullable()->unique(); // ✅ idempotencia

            $table->string('telegram_file_id')->nullable();
            $table->string('telegram_file_unique_id')->nullable();

            $table->json('payload')->nullable();

            $table->boolean('leido')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->string('estado_envio')->nullable(); // pending|sent|failed (salientes)
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['chat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensajes');
    }
};
