<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_historial_objetivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercial_id')->constrained('users')->cascadeOnDelete();
            $table->integer('año');
            $table->integer('mes');
            $table->boolean('alcanzo_todos_minimos_obligatorios')->default(false);
            $table->decimal('total_comisiones_calculado', 10, 2)->default(0);
            $table->decimal('total_bonos', 10, 2)->default(0);
            $table->decimal('total_final', 10, 2)->default(0);
            $table->enum('estado', ['borrador', 'aprobada', 'pagada'])->default('borrador');
            $table->timestamps();

            $table->unique(['comercial_id', 'año', 'mes']);
            $table->index(['comercial_id', 'año']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_historial_objetivos');
    }
};
