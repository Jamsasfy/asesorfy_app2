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
    Schema::table('users', function (Blueprint $table) {
        // Esta es la "bandera" de seguridad
        $table->boolean('email_bienvenida_enviado')->default(false)->after('acceso_app');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Elimina la columna si la migración se revierte
            $table->dropColumn('email_bienvenida_enviada');
        });
    }
};
