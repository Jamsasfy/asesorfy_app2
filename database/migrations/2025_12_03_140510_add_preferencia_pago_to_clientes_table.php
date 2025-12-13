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
        // Valores: 'tarjeta', 'domiciliacion'
        $table->string('preferencia_pago_recurrente')->nullable()->default('tarjeta')->after('iban_asesorfy');
    });
}

public function down(): void
{
    Schema::table('clientes', function (Blueprint $table) {
        $table->dropColumn('preferencia_pago_recurrente');
    });
}
};
