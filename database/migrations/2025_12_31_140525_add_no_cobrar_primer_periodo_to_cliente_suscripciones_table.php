<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cliente_suscripciones', function (Blueprint $table) {
            $table->boolean('no_cobrar_primer_periodo')
                ->default(false)
                ->after('descuento_duracion_meses');
        });
    }

    public function down(): void
    {
        Schema::table('cliente_suscripciones', function (Blueprint $table) {
            $table->dropColumn('no_cobrar_primer_periodo');
        });
    }
};
