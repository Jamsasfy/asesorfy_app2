<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_contratos_incentivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercial_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', ['base', 'anexo'])->default('base');
            $table->foreignId('contrato_base_id')->nullable()->constrained('comercial_contratos_incentivos')->onDelete('set null');
            $table->json('reglas_snapshot')->comment('Snapshot de reglas en el momento de generar');
            $table->json('config_snapshot')->comment('Meses consecutivos/alternos del momento');
            $table->dateTime('fecha_envio');
            $table->dateTime('fecha_firma')->nullable();
            $table->string('token_firma', 64)->unique();
            $table->string('pdf_path')->nullable()->comment('Path del PDF firmado');
            $table->string('hash_documento', 64)->comment('SHA-256 del documento');
            $table->string('ip_firma', 45)->nullable();
            $table->text('user_agent_firma')->nullable();
            $table->timestamps();

            $table->index('comercial_id');
            $table->index('tipo');
            $table->index('fecha_firma');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_contratos_incentivos');
    }
};
