<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversaciones', function (Blueprint $table) {
            $table->id();

            $table->string('tipo')->default('cliente'); // futuro: equipo/sistema
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('asesor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedBigInteger('telegram_chat_id')->nullable()->unique();

            $table->string('estado')->default('abierta'); // abierta|archivada|cerrada
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['asesor_id', 'estado', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversaciones');
    }
};
