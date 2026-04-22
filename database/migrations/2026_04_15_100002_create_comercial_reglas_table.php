<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_reglas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercial_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('regla_id')->constrained('comision_reglas')->cascadeOnDelete();
            $table->boolean('es_obligatoria')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['comercial_id', 'regla_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_reglas');
    }
};
