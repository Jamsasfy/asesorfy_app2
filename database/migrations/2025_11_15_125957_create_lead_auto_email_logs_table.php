<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_auto_email_logs', function (Blueprint $table) {
            $table->id();

            // Relación principal
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // Estado del lead en el momento del envío
            $table->string('estado')->index(); // p.ej. 'esperando_informacion'
            $table->unsignedTinyInteger('intento')->default(1); // nº de intento (1..5)

            // Identificación de la plantilla
            $table->string('template_identifier')->nullable(); // p.ej. 'esperando_informacion_3'
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable(); // primeros X caracteres del body

            // Info de programación / envío real
            $table->dateTime('scheduled_at')->nullable(); // cuándo *debería* salir
            $table->dateTime('sent_at')->nullable();      // cuándo realmente se envió

            // Estado del envío
            $table->string('status')->default('pending');
            // 'pending', 'sent', 'failed', 'skipped', 'rate_limited'

            // Proveedor / transporte
            $table->string('mail_driver')->nullable(); // smtp, mailgun, ses...
            $table->string('provider')->nullable();    // mailgun, ses...
            $table->string('provider_message_id')->nullable();

            // Errores / info extra
            $table->boolean('rate_limited')->default(false);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            // Quién disparó el cambio de estado, opcional
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('trigger_source')->nullable();
            // 'auto_job', 'manual', 'admin_override', etc.

            // JSON para campos flexibles futuros
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['estado', 'status']);
            $table->index(['scheduled_at', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_auto_email_logs');
    }
};
