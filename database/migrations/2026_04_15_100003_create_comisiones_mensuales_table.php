<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercial_id')->constrained('users')->cascadeOnDelete();
            $table->integer('año');
            $table->integer('mes');
            $table->foreignId('regla_id')->constrained('comision_reglas')->cascadeOnDelete();

            $table->decimal('facturacion_bruta', 10, 2)->default(0);
            $table->decimal('bajas_mes', 10, 2)->default(0);
            $table->decimal('facturacion_neta', 10, 2)->default(0);
            $table->decimal('minimo_aplicable', 10, 2)->default(0);
            $table->decimal('base_comisionable', 10, 2)->default(0);
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->decimal('importe_comision_calculado', 10, 2)->default(0);

            $table->json('bonos')->nullable();
            $table->decimal('total_bonos', 10, 2)->default(0);
            $table->decimal('importe_final', 10, 2)->default(0);

            $table->enum('estado', ['borrador', 'aprobada', 'pagada'])->default('borrador');
            $table->boolean('alcanzo_minimo')->default(false);
            $table->boolean('es_regla_obligatoria')->default(false);

            $table->foreignId('aprobada_por_id')->nullable()->constrained('users');
            $table->timestamp('aprobada_at')->nullable();
            $table->timestamp('pagada_at')->nullable();
            $table->string('pagada_referencia')->nullable();

            $table->timestamps();

            $table->unique(['comercial_id', 'año', 'mes', 'regla_id']);
            $table->index(['comercial_id', 'año', 'mes']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_mensuales');
    }
};
