<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('pago_inicial_metodo', 20)
                ->nullable()
                ->after('importe_total'); // o donde te venga mejor

            $table->string('pago_inicial_notas', 255)
                ->nullable()
                ->after('pago_inicial_metodo');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['pago_inicial_metodo', 'pago_inicial_notas']);
        });
    }
};
