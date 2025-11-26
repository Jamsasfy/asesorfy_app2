<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('plantilla_contratos', function (Blueprint $table) {
        $table->id();
        $table->string('clave')->unique(); // Ej: 'anexo_1', 'clausula_rgpd'
        $table->string('titulo');          // Ej: 'Anexo I - Servicios Recurrentes'
        $table->longText('contenido');     // El texto legal con formato HTML
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantilla_contratos');
    }
};
