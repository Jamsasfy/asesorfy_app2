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
    Schema::table('servicios', function (Blueprint $table) {
        // ID del "Producto" en Stripe (ej: "Fiscal Autónomos")
        $table->string('stripe_product_id')->nullable()->index()->after('ciclo_facturacion');
        
        // ID del "Precio" recurrente en Stripe (ej: "price_1Me3...", los 50€/mes)
        $table->string('stripe_price_id')->nullable()->index()->after('stripe_product_id');
    });
}

public function down(): void
{
    Schema::table('servicios', function (Blueprint $table) {
        $table->dropColumn(['stripe_product_id', 'stripe_price_id']);
    });
}
};
