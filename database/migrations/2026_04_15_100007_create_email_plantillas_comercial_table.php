<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_plantillas_comercial', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->string('asunto');
            $table->string('asunto_rrhh')->nullable();
            $table->text('contenido_html');
            $table->text('contenido_html_rrhh')->nullable();
            $table->json('variables_disponibles');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_plantillas_comercial');
    }
};
