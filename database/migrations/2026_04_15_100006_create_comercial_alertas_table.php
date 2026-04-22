<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercial_id')->constrained('users')->cascadeOnDelete();
            $table->integer('año');
            $table->integer('mes');
            $table->enum('tipo', ['no_alcanza_minimo', 'resultado_positivo', 'despido_automatico']);
            $table->foreignId('regla_id')->nullable()->constrained('comision_reglas');
            $table->decimal('facturacion_neta', 10, 2)->nullable();
            $table->decimal('minimo_requerido', 10, 2)->nullable();
            $table->decimal('importe_comision', 10, 2)->nullable();
            $table->timestamp('email_enviado_at')->nullable();
            $table->string('plantilla_codigo')->nullable();
            $table->json('destinatarios')->nullable();
            $table->timestamps();

            $table->index(['comercial_id', 'año', 'mes']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_alertas');
    }
};
