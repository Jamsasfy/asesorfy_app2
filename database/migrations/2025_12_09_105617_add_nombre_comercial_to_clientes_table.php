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
        Schema::table('clientes', function (Blueprint $table) {
            // Añadimos la columna 'nombre_comercial' después de 'razon_social'.
            // Es nullable porque no todos los clientes (especialmente S.L.) lo usarán.
            $table->string('nombre_comercial')->nullable()->after('razon_social');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Eliminamos la columna si hacemos rollback
            $table->dropColumn('nombre_comercial');
        });
    }
};
