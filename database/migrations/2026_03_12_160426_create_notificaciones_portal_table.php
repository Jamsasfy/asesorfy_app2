<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_portal', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('mensaje');
            $table->enum('tipo', ['info', 'aviso', 'urgente', 'critico'])->default('info');
            $table->json('canales')->nullable(); // ['plataforma', 'email', 'telegram']
            $table->boolean('bloquea_portal')->default(false);
            $table->enum('destinatarios', ['todos', 'por_servicio', 'cliente_especifico'])->default('todos');
            $table->json('filtro_servicios')->nullable(); // IDs de servicios
            $table->json('filtro_clientes')->nullable(); // IDs de clientes específicos
            $table->boolean('activa')->default(true);
            $table->timestamp('fecha_publicacion')->nullable();
            $table->timestamp('fecha_caducidad')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('activa');
            $table->index('fecha_publicacion');
            $table->index('tipo');
        });

        Schema::create('notificacion_portal_vistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notificacion_portal_id')->constrained('notificaciones_portal')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('visto_en_plataforma')->default(false);
            $table->timestamp('leido_at')->nullable();
            $table->string('canal_recibido')->nullable(); // email, telegram, plataforma
            $table->timestamps();

            $table->unique(['notificacion_portal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion_portal_vistas');
        Schema::dropIfExists('notificaciones_portal');
    }
};
