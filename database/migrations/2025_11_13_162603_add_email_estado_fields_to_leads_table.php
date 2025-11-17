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
        Schema::table('leads', function (Blueprint $table) {
            // Número de emails automáticos enviados en el estado actual
            $table->unsignedTinyInteger('estado_email_intentos')
                ->default(0)
                ->after('estado');

            // Última fecha/hora en la que se envió un email automático para el estado actual
            $table->timestamp('estado_email_ultima_fecha')
                ->nullable()
                ->after('estado_email_intentos');

            // Última vez que alguien del equipo interactuó manualmente con el lead
            $table->timestamp('ultima_interaccion_manual_at')
                ->nullable()
                ->after('estado_email_ultima_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'estado_email_intentos',
                'estado_email_ultima_fecha',
                'ultima_interaccion_manual_at',
            ]);
        });
    }
};
