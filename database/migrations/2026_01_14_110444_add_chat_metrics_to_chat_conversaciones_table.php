<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('last_cliente_message_id')->nullable()->after('last_message_at');
            $table->dateTime('last_cliente_at')->nullable()->after('last_cliente_message_id');

            $table->unsignedBigInteger('last_asesor_message_id')->nullable()->after('last_cliente_at');
            $table->dateTime('last_asesor_at')->nullable()->after('last_asesor_message_id');

            $table->boolean('pendiente_respuesta')->default(false)->after('last_asesor_at');
            $table->dateTime('pendiente_since_at')->nullable()->after('pendiente_respuesta');

            // tiempo (segundos) que tardó el asesor en responder al último mensaje del cliente (cuando se responde)
            $table->unsignedInteger('last_response_seconds')->nullable()->after('pendiente_since_at');

            $table->index(['tipo', 'asesor_id']);
            $table->index(['pendiente_respuesta', 'pendiente_since_at']);
            $table->index(['last_cliente_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversaciones', function (Blueprint $table) {
            $table->dropIndex(['tipo', 'asesor_id']);
            $table->dropIndex(['pendiente_respuesta', 'pendiente_since_at']);
            $table->dropIndex(['last_cliente_at']);

            $table->dropColumn([
                'last_cliente_message_id',
                'last_cliente_at',
                'last_asesor_message_id',
                'last_asesor_at',
                'pendiente_respuesta',
                'pendiente_since_at',
                'last_response_seconds',
            ]);
        });
    }
};
