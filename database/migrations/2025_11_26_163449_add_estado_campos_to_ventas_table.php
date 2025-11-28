<?php

use App\Enums\VentaEstadoEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Estado de la venta (pendiente / completada / cancelada)
            $table->string('estado')
                ->default(VentaEstadoEnum::PENDIENTE->value)
                ->after('id'); // ajusta el after donde más te convenga

            // Marca cuándo el cliente firmó el contrato
            $table->timestamp('contrato_firmado_at')
                ->nullable()
                ->after('estado');

            // Marca cuándo consideramos la venta cerrada de verdad
            $table->timestamp('confirmada_at')
                ->nullable()
                ->after('contrato_firmado_at');

            // Indica si esta venta requiere pago inicial (por servicios únicos)
            $table->boolean('requiere_pago_inicial')
                ->default(false)
                ->after('confirmada_at');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'estado',
                'contrato_firmado_at',
                'confirmada_at',
                'requiere_pago_inicial',
            ]);
        });
    }
};
