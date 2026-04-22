<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comision_mensual_id')->constrained('comisiones_mensuales')->cascadeOnDelete();
            $table->enum('tipo', ['factura', 'baja']);

            $table->foreignId('factura_id')->nullable()->constrained('facturas');
            $table->foreignId('factura_item_id')->nullable()->constrained('factura_items');
            $table->foreignId('servicio_id')->nullable()->constrained('servicios');
            $table->foreignId('cliente_suscripcion_id')->nullable()->constrained('cliente_suscripciones');

            $table->date('fecha_baja')->nullable();
            $table->integer('meses_activo')->nullable();
            $table->foreignId('comision_original_id')->nullable()->constrained('comisiones_mensuales');

            $table->decimal('importe', 10, 2);
            $table->timestamp('created_at');

            $table->index(['comision_mensual_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_detalles');
    }
};
