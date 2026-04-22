<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_reglas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo_servicio', ['recurrente', 'unico']);
            $table->json('servicios_ids')->nullable();
            $table->decimal('minimo_mensual', 10, 2)->default(0);
            $table->decimal('porcentaje_comision', 5, 2);
            $table->integer('penalizacion_baja_antes_meses')->default(3);
            $table->boolean('es_obligatoria')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_reglas');
    }
};
