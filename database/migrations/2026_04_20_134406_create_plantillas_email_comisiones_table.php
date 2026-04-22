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
        Schema::create('plantillas_email_comisiones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique()->comment('comision_supera o comision_no_supera');
            $table->string('nombre');
            $table->string('asunto');
            $table->longText('contenido_html');
            $table->json('variables_disponibles')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantillas_email_comisiones');
    }
};
