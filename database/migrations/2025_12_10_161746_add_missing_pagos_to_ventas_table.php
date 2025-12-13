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
        Schema::table('ventas', function (Blueprint $table) {
            // Añadimos las columnas que faltan después de 'pago_inicial_notas'
            $table->dateTime('pago_inicial_fecha')->nullable()->after('pago_inicial_notas');
            $table->string('pago_inicial_referencia')->nullable()->after('pago_inicial_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['pago_inicial_fecha', 'pago_inicial_referencia']);
        });
    }
};
