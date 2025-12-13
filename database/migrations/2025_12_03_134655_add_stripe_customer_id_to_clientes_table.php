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
        // Creamos la columna para guardar el ID del cliente de Stripe (ej: cus_N8s9...)
        $table->string('stripe_customer_id')->nullable()->index()->after('email_contacto');
    });
}

public function down(): void
{
    Schema::table('clientes', function (Blueprint $table) {
        $table->dropColumn('stripe_customer_id');
    });
}
};
