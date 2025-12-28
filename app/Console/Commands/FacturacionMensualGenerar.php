<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Services\FacturacionService;
use App\Services\ConfiguracionService;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Enums\FacturaEstadoEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturacionMensualGenerar extends Command // <-- El nombre de la clase se mantiene
{
    // CAMBIO CLAVE: La firma del comando, ahora es solo para recurrentes
    protected $signature = 'facturacion:generar-recurrentes {--fecha=}'; 
    protected $description = 'Genera las facturas mensuales para las suscripciones recurrentes activas.';

    public function handle(): void
    {
        $hoy = $this->option('fecha') ? Carbon::parse($this->option('fecha'))->startOfDay() : Carbon::now()->startOfDay();
        $this->info("Iniciando facturación para el día: {$hoy->toDateString()}");

        // --- FILTRAMOS SOLO POR SUSCRIPCIONES RECURRENTES ---
        $suscripciones = ClienteSuscripcion::query()
            ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
            ->whereHas('servicio', fn($q) => $q->where('tipo', ServicioTipoEnum::RECURRENTE)) // <-- FILTRO CLAVE
            ->whereDate('proxima_fecha_facturacion', '<=', $hoy)
            ->get()
            ->groupBy('cliente_id');

        if ($suscripciones->isEmpty()) {
            $this->info('No hay suscripciones recurrentes para facturar hoy.');
            return;
        }

        $this->info("Se encontraron suscripciones recurrentes para {$suscripciones->count()} clientes.");
        $ivaPorcentaje = ConfiguracionService::get('IVA_general', 21.00); 

        foreach ($suscripciones as $suscripcionesDelCliente) {
            DB::transaction(function () use ($suscripcionesDelCliente, $hoy, $ivaPorcentaje) {
                try {
                    $datosFactura = FacturacionService::generarSiguienteNumeroFactura();

                    $factura = Factura::create([
                        'cliente_id'        => $suscripcionesDelCliente->first()->cliente_id,
                        'serie'             => $datosFactura['serie'],
                        'numero_factura'    => $datosFactura['numero_factura'],
                        'estado'            => FacturaEstadoEnum::PENDIENTE_PAGO, // PENDIENTE_PAGO
                        'metodo_pago'       => 'stripe',                 // Método de pago 'stripe'
                        'fecha_emision'     => $hoy,
                        'fecha_vencimiento' => $hoy->copy()->addDays(15),
                        'base_imponible'    => 0, 'total_iva' => 0, 'total_factura' => 0,
                    ]);

                    $this->line(" -> Creada Factura {$factura->numero_factura} para el cliente ID {$factura->cliente_id}");

                    $baseTotal = 0;
                    $ivaTotal = 0;

                    foreach ($suscripcionesDelCliente as $suscripcion) {
                        $precioUnitarioBaseReal = ($suscripcion->cantidad > 0) 
                                                    ? ($suscripcion->precio_acordado / $suscripcion->cantidad) 
                                                    : 0; 
                        $cantidadSuscripcion = $suscripcion->cantidad;

                        $descripcion = $suscripcion->nombre_final;
                        $descripcion .= ' - Periodo ' . $hoy->format('m/Y'); // Descripción para recurrentes
                        if ($suscripcion->descuento_descripcion) {
                            $descripcion .= " ({$suscripcion->descuento_descripcion})";
                        }

                        $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal;
                        $importeDescuentoAplicadoALinea = 0;
                        $subtotalLineaBaseCalculado = $precioUnitarioBaseReal * $cantidadSuscripcion;

                        $descuentoVigente = $suscripcion->descuento_tipo && $suscripcion->descuento_valido_hasta && $hoy->lte($suscripcion->descuento_valido_hasta);

                        if ($descuentoVigente) {
                            if ($suscripcion->descuento_tipo === 'porcentaje') {
                                $descuentoPorcentajeValor = $suscripcion->descuento_valor / 100;
                                $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal * (1 - $descuentoPorcentajeValor);
                                $importeDescuentoAplicadoALinea = ($precioUnitarioBaseReal - $precioUnitarioDespuesPorcentajeDto) * $cantidadSuscripcion;
                            } 
                        }

                        $precioUnitarioCalculadoParaFacturaItem = $precioUnitarioDespuesPorcentajeDto;
                        $subtotalLineaCalculado = $precioUnitarioCalculadoParaFacturaItem * $cantidadSuscripcion;

                        if ($descuentoVigente && in_array($suscripcion->descuento_tipo, ['fijo', 'precio_final'])) {
                            if ($suscripcion->descuento_tipo === 'fijo') {
                                $descuentoTotalFijo = $suscripcion->descuento_valor;
                                $subtotalLineaCalculado = max(0, $subtotalLineaBaseCalculado - $descuentoTotalFijo);
                                $importeDescuentoAplicadoALinea += $descuentoTotalFijo;
                            } else { // 'precio_final'
                                $precioFinalDeseado = $suscripcion->descuento_valor;
                                $descuentoTotalFijo = $subtotalLineaBaseCalculado - $precioFinalDeseado;
                                $subtotalLineaCalculado = $precioFinalDeseado;
                                $importeDescuentoAplicadoALinea = max(0, $descuentoTotalFijo);
                            }
                            $precioUnitarioCalculadoParaFacturaItem = ($cantidadSuscripcion > 0) ? ($subtotalLineaCalculado / $cantidadSuscripcion) : 0;
                        } else {
                            $subtotalLineaCalculado = $subtotalLineaBaseCalculado;
                        }

                        $ivaItem = $subtotalLineaCalculado * ($ivaPorcentaje / 100);

                        $factura->items()->create([
                            'cliente_suscripcion_id' => $suscripcion->id,
                            'servicio_id'        => $suscripcion->servicio_id,
                            'descripcion'        => $descripcion,
                            'cantidad'           => $cantidadSuscripcion,
                            'precio_unitario'    => round($precioUnitarioBaseReal, 2),
                            'precio_unitario_aplicado' => round($precioUnitarioCalculadoParaFacturaItem, 2),
                            'importe_descuento'  => round($importeDescuentoAplicadoALinea, 2),
                            'porcentaje_iva'     => $ivaPorcentaje,
                            'subtotal'           => round($subtotalLineaCalculado, 2),
                        ]);

                        $baseTotal += $subtotalLineaCalculado;
                        $ivaTotal += $ivaItem;

                        // Solo actualizamos la próxima fecha de facturación para suscripciones recurrentes
                        $suscripcion->update([
                            'proxima_fecha_facturacion' => $suscripcion->proxima_fecha_facturacion->copy()->addMonth()
                        ]);
                    }

                    // Actualizamos los totales de la factura
                    $factura->update([
                        'base_imponible' => round($baseTotal, 2),
                        'total_iva'      => round($ivaTotal, 2),
                        'total_factura'  => round($baseTotal + $ivaTotal, 2),
                    ]);
                } catch (Exception $e) {
                    Log::error("Error al generar facturas recurrentes para cliente ID {$suscripcionesDelCliente->first()->cliente_id}: " . $e->getMessage());
                    $this->error("Error al procesar cliente ID {$suscripcionesDelCliente->first()->cliente_id}: " . $e->getMessage());
                }
            });
        }

        $this->info('¡Proceso de facturación de recurrentes finalizado!');
    }
}