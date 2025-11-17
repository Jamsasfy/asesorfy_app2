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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Nombre interno de la plantilla (ej: "Propuesta enviada")
            $table->string('slug')->unique(); // Identificador único (ej: "propuesta_enviada")
            $table->string('asunto'); // Asunto del correo
            $table->longText('contenido_html'); // Cuerpo del email (HTML)
            $table->boolean('activo')->default(true); // Permitir desactivarla
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
