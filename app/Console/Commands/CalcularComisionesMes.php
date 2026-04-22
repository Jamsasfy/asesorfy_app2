<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\ComisionRegla;
use App\Models\ComisionMensual;
use App\Models\ComisionDetalle;
use App\Models\ComercialHistorialObjetivo;
use App\Models\ComercialAlerta;
use App\Models\ConfiguracionComisiones;
use App\Models\ClienteSuscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CalcularComisionesMes extends Command
{
    protected $signature = 'comisiones:calcular-mes {--mes= : Mes en formato YYYY-MM (default: mes anterior)}';
    protected $description = 'Calcula comisiones mensuales para comerciales';

    public function handle(): void
    {
        $mes = $this->option('mes');
        $fecha = $mes ? Carbon::parse($mes . '-01') : now()->subMonth();

        $año     = $fecha->year;
        $mesNum  = $fecha->month;
        $mesNombre = ucfirst($fecha->locale('es')->monthName);

        $this->info("Calculando comisiones para {$mesNombre} {$año}...\n");

        $comerciales = User::whereHas('roles', fn ($q) => $q->where('name', 'comercial'))->get();

        if ($comerciales->isEmpty()) {
            $this->warn('No hay comerciales en el sistema.');
            return;
        }

        $this->info("Comerciales encontrados: {$comerciales->count()}\n");

        foreach ($comerciales as $comercial) {
            $this->line("Procesando: {$comercial->name}");
            $this->procesarComercial($comercial, $año, $mesNum, $mesNombre);
        }

        $this->info("\nProceso completado");
    }

    protected function procesarComercial($comercial, $año, $mes, $mesNombre): void
    {
        $reglasAsignadas = DB::table('comercial_reglas')
            ->where('comercial_id', $comercial->id)
            ->where('activa', true)
            ->get();

        if ($reglasAsignadas->isEmpty()) {
            $this->warn("  No tiene reglas asignadas\n");
            return;
        }

        $comisionesPorRegla               = [];
        $algunaObligatoriaNoAlcanzada     = false;

        foreach ($reglasAsignadas as $asignacion) {
            $regla = ComisionRegla::find($asignacion->regla_id);

            if (!$regla || !$regla->activa) {
                continue;
            }

            $this->line("  → Regla: {$regla->nombre}");

            $facturacionBruta = $this->calcularFacturacion($comercial->id, $año, $mes, $regla);
            $bajasMes         = $this->calcularBajas($comercial->id, $año, $mes, $regla);
            $facturacionNeta  = $facturacionBruta + $bajasMes;

            $this->line("    Bruta: €{$facturacionBruta} | Bajas: €{$bajasMes} | Neta: €{$facturacionNeta}");

            $alcanzaMinimo   = $facturacionNeta >= $regla->minimo_mensual;
            $baseComisionable = $alcanzaMinimo ? max(0, $facturacionNeta - $regla->minimo_mensual) : 0;
            $importeComision  = $baseComisionable * ($regla->porcentaje_comision / 100);

            $this->line("    Mínimo: €{$regla->minimo_mensual} | " . ($alcanzaMinimo ? 'ALCANZA' : 'NO ALCANZA') . " | Comisión: €{$importeComision}");

            $comision = ComisionMensual::updateOrCreate(
                [
                    'comercial_id' => $comercial->id,
                    'año'          => $año,
                    'mes'          => $mes,
                    'regla_id'     => $regla->id,
                ],
                [
                    'facturacion_bruta'          => $facturacionBruta,
                    'bajas_mes'                  => $bajasMes,
                    'facturacion_neta'           => $facturacionNeta,
                    'minimo_aplicable'           => $regla->minimo_mensual,
                    'base_comisionable'          => $baseComisionable,
                    'porcentaje'                 => $regla->porcentaje_comision,
                    'importe_comision_calculado' => $importeComision,
                    'total_bonos'                => 0,
                    'importe_final'              => $importeComision,
                    'estado'                     => 'borrador',
                    'alcanzo_minimo'             => $alcanzaMinimo,
                    'es_regla_obligatoria'       => $asignacion->es_obligatoria,
                ]
            );

            $this->guardarDetalles($comision, $comercial->id, $año, $mes, $regla);

            $comisionesPorRegla[] = $comision;

            if ($asignacion->es_obligatoria && !$alcanzaMinimo) {
                $algunaObligatoriaNoAlcanzada = true;
                $this->warn("    REGLA OBLIGATORIA NO ALCANZADA");
            }

            $this->newLine();
        }

        // Si alguna obligatoria no se alcanzó → todas las comisiones a 0
        if ($algunaObligatoriaNoAlcanzada) {
            $this->warn("  REGLA OBLIGATORIA NO ALCANZADA → Todas las comisiones a 0\n");
            foreach ($comisionesPorRegla as $comision) {
                $comision->update([
                    'importe_comision_calculado' => 0,
                    'importe_final'              => 0,
                ]);
            }
        }

        // Verificar si alguna regla alcanzó mínimo
        $algunaAlcanzoMinimo = false;
        foreach ($comisionesPorRegla as $comision) {
            if ($comision->alcanzo_minimo) {
                $algunaAlcanzoMinimo = true;
                break;
            }
        }

        $totalComisiones = $algunaObligatoriaNoAlcanzada ? 0 : collect($comisionesPorRegla)->sum('importe_comision_calculado');

        $alcanzaTodos = !$algunaObligatoriaNoAlcanzada && $algunaAlcanzoMinimo;

        // Preparar datos para email futuro (se envía al aprobar, no al calcular)
        $desglosePorRegla = [];
        foreach ($comisionesPorRegla as $comision) {
            $comisionTeorica = $comision->base_comisionable * ($comision->porcentaje / 100);

            $desglosePorRegla[] = [
                'regla_id'         => $comision->regla_id,
                'regla_nombre'     => $comision->regla->nombre ?? '—',
                'facturacion_neta' => $comision->facturacion_neta,
                'minimo_requerido' => $comision->minimo_aplicable,
                'porcentaje_comision' => $comision->porcentaje,
                'alcanzo_minimo'   => $comision->alcanzo_minimo,
                'comision'         => $comision->importe_comision_calculado,
                'comision_teorica' => $comisionTeorica,
            ];
        }

        ComercialHistorialObjetivo::updateOrCreate(
            [
                'comercial_id' => $comercial->id,
                'año'          => $año,
                'mes'          => $mes,
            ],
            [
                'alcanzo_todos_minimos_obligatorios' => $alcanzaTodos,
                'total_comisiones_calculado'         => $totalComisiones,
                'total_bonos'                        => 0,
                'total_final'                        => $totalComisiones,
                'estado'                             => 'borrador',
                'datos_adicionales'                  => ['desglose_reglas' => $desglosePorRegla],
            ]
        );

        $this->line("  💾 Guardado en borrador - " . ($alcanzaTodos ? "✅ Alcanza mínimos" : "❌ No alcanza mínimos"));
    }

    protected function calcularFacturacion($comercialId, $año, $mes, $regla): float
    {
        $query = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->join('leads', 'leads.id', '=', 'ventas.lead_id')
            ->join('servicios', 'servicios.id', '=', 'venta_items.servicio_id')
            ->where('leads.asignado_id', $comercialId)
            ->where('ventas.estado', 'completada')
            ->whereYear('ventas.fecha_venta', $año)
            ->whereMonth('ventas.fecha_venta', $mes)
            ->where('servicios.tipo', $regla->tipo_servicio);

        if ($regla->servicios_ids) {
            $query->whereIn('venta_items.servicio_id', $regla->servicios_ids);
        }

        return (float) $query->sum('venta_items.precio_unitario');
    }

    protected function calcularBajas($comercialId, $año, $mes, $regla): float
    {
        if ($regla->tipo_servicio !== 'recurrente') {
            return 0;
        }

        $bajas = ClienteSuscripcion::whereHas('ventaOrigen.lead', fn ($q) => $q->where('asignado_id', $comercialId))
            ->whereIn('estado', ['cancelada', 'inactiva'])
            ->whereYear('fecha_fin', $año)
            ->whereMonth('fecha_fin', $mes)
            ->whereNotNull('fecha_inicio')
            ->get();

        $totalBajas = 0;

        foreach ($bajas as $baja) {
            $mesesActivo = $baja->fecha_inicio->diffInMonths($baja->fecha_fin);

            if ($mesesActivo < $regla->penalizacion_baja_antes_meses) {
                $totalBajas += -1 * ($baja->precio_acordado * $baja->cantidad);
            }
        }

        return $totalBajas;
    }

    protected function guardarDetalles($comision, $comercialId, $año, $mes, $regla): void
    {
        ComisionDetalle::where('comision_mensual_id', $comision->id)->delete();

        $query = DB::table('factura_items')
            ->select('factura_items.*', 'facturas.id as factura_id')
            ->join('facturas', 'facturas.id', '=', 'factura_items.factura_id')
            ->join('ventas', 'ventas.id', '=', 'facturas.venta_id')
            ->join('leads', 'leads.id', '=', 'ventas.lead_id')
            ->join('servicios', 'servicios.id', '=', 'factura_items.servicio_id')
            ->where('leads.asignado_id', $comercialId)
            ->where('facturas.estado', 'pagada')
            ->whereYear('facturas.fecha_emision', $año)
            ->whereMonth('facturas.fecha_emision', $mes)
            ->where('servicios.tipo', $regla->tipo_servicio);

        if ($regla->servicios_ids) {
            $query->whereIn('factura_items.servicio_id', $regla->servicios_ids);
        }

        foreach ($query->get() as $item) {
            ComisionDetalle::create([
                'comision_mensual_id'   => $comision->id,
                'tipo'                  => 'factura',
                'factura_id'            => $item->factura_id,
                'factura_item_id'       => $item->id,
                'servicio_id'           => $item->servicio_id,
                'cliente_suscripcion_id' => $item->cliente_suscripcion_id,
                'importe'               => $item->subtotal,
                'created_at'            => now(),
            ]);
        }

        if ($regla->tipo_servicio === 'recurrente') {
            $bajas = ClienteSuscripcion::whereHas('ventaOrigen.lead', fn ($q) => $q->where('asignado_id', $comercialId))
                ->whereIn('estado', ['cancelada', 'inactiva'])
                ->whereYear('fecha_fin', $año)
                ->whereMonth('fecha_fin', $mes)
                ->whereNotNull('fecha_inicio')
                ->get();

            foreach ($bajas as $baja) {
                $mesesActivo = $baja->fecha_inicio->diffInMonths($baja->fecha_fin);

                if ($mesesActivo < $regla->penalizacion_baja_antes_meses) {
                    ComisionDetalle::create([
                        'comision_mensual_id'   => $comision->id,
                        'tipo'                  => 'baja',
                        'cliente_suscripcion_id' => $baja->id,
                        'fecha_baja'            => $baja->fecha_fin,
                        'meses_activo'          => $mesesActivo,
                        'importe'               => -1 * ($baja->precio_acordado * $baja->cantidad),
                        'created_at'            => now(),
                    ]);
                }
            }
        }
    }

    protected function enviarEmails($comercial, $año, $mes, $mesNombre, $alcanzaTodos, $totalComisiones, $comisionesPorRegla): void
    {
        $plantillaCodigo = $alcanzaTodos ? 'comision_positivo' : 'comision_no_minimo';

        $plantilla = \App\Models\EmailPlantillaComercial::where('codigo', $plantillaCodigo)
            ->where('activa', true)
            ->first();

        if (!$plantilla) {
            $this->warn("  Plantilla {$plantillaCodigo} no encontrada");
            return;
        }

        $mesesConsecutivos = $this->contarMesesConsecutivosSinMinimo($comercial->id, $año, $mes);

        // Datos de la primera regla para el email
        $primeraComision = collect($comisionesPorRegla)->first();

        $variables = [
            'comercial_nombre'              => $comercial->name,
            'mes'                           => $mesNombre,
            'año'                           => $año,
            'total_comision'                => '€' . number_format($totalComisiones, 2, ',', '.'),
            'facturacion_neta'              => '€' . number_format($primeraComision?->facturacion_neta ?? 0, 2, ',', '.'),
            'minimo_requerido'              => '€' . number_format($primeraComision?->minimo_aplicable ?? 0, 2, ',', '.'),
            'base_comisionable'             => '€' . number_format($primeraComision?->base_comisionable ?? 0, 2, ',', '.'),
            'porcentaje'                    => $primeraComision?->porcentaje ?? 0,
            'diferencia'                    => '€' . number_format(max(0, ($primeraComision?->minimo_aplicable ?? 0) - ($primeraComision?->facturacion_neta ?? 0)), 2, ',', '.'),
            'meses_consecutivos_sin_minimo' => $mesesConsecutivos,
        ];

        $asunto    = $this->reemplazarVariables($plantilla->asunto, $variables);
        $contenido = $this->reemplazarVariables($plantilla->contenido_html, $variables);

        try {
            Mail::send([], [], function ($message) use ($comercial, $asunto, $contenido) {
                $message->to($comercial->email)->subject($asunto)->html($contenido);
            });

            ComercialAlerta::create([
                'comercial_id'     => $comercial->id,
                'año'              => $año,
                'mes'              => $mes,
                'tipo'             => $alcanzaTodos ? 'resultado_positivo' : 'no_alcanza_minimo',
                'importe_comision' => $totalComisiones,
                'email_enviado_at' => now(),
                'plantilla_codigo' => $plantillaCodigo,
                'destinatarios'    => [$comercial->email],
            ]);

            $this->info("  Email enviado a {$comercial->email}");
        } catch (\Exception $e) {
            $this->error("  Error enviando email: " . $e->getMessage());
            Log::error('Error enviando email comisión', ['comercial_id' => $comercial->id, 'error' => $e->getMessage()]);
        }
    }

    protected function verificarDespido($comercial, $año, $mes, $mesNombre): void
    {
        $config = ConfiguracionComisiones::first();

        if (!$config) {
            return;
        }

        $mesesConsecutivos = $this->contarMesesConsecutivosSinMinimo($comercial->id, $año, $mes);
        $mesesAlternos     = $this->contarMesesAlternosSinMinimo($comercial->id, $año, $mes, $config->periodo_meses_alternos);

        $cumpleConsecutivos = $mesesConsecutivos >= $config->meses_consecutivos_despido;
        $cumpleAlternos     = $mesesAlternos >= $config->meses_alternos_despido;

        if (!$cumpleConsecutivos && !$cumpleAlternos) {
            return;
        }

        $this->warn("  CONDICIONES DE DESPIDO CUMPLIDAS");

        $condicion = $cumpleConsecutivos
            ? "{$mesesConsecutivos} meses consecutivos sin alcanzar mínimo"
            : "{$mesesAlternos} meses alternos sin alcanzar mínimo en {$config->periodo_meses_alternos} meses";

        $this->enviarEmailDespido($comercial, $año, $mes, $condicion, $mesesConsecutivos, $mesesAlternos, $config);
    }

    protected function enviarEmailDespido($comercial, $año, $mes, $condicion, $mesesConsecutivos, $mesesAlternos, $config): void
    {
        $plantilla = \App\Models\EmailPlantillaComercial::where('codigo', 'despido_aviso')
            ->where('activa', true)
            ->first();

        if (!$plantilla) {
            return;
        }

        $variables = [
            'comercial_nombre'   => $comercial->name,
            'condicion_cumplida' => $condicion,
            'meses_consecutivos' => $mesesConsecutivos,
            'meses_alternos'     => $mesesAlternos,
            'periodo_meses'      => $config->periodo_meses_alternos,
        ];

        // Email al comercial
        try {
            $asunto    = $this->reemplazarVariables($plantilla->asunto, $variables);
            $contenido = $this->reemplazarVariables($plantilla->contenido_html, $variables);

            Mail::send([], [], fn ($m) => $m->to($comercial->email)->subject($asunto)->html($contenido));
            $this->warn("  Email de despido enviado a {$comercial->email}");
        } catch (\Exception $e) {
            $this->error("  Error: " . $e->getMessage());
        }

        // Email a RRHH
        if (!empty($config->emails_notificacion_despido)) {
            $asuntoRRHH    = $this->reemplazarVariables($plantilla->asunto_rrhh ?? 'ALERTA - Condiciones de despido', $variables);
            $contenidoRRHH = $this->reemplazarVariables($plantilla->contenido_html_rrhh ?? $plantilla->contenido_html, $variables);

            foreach ($config->emails_notificacion_despido as $emailRRHH) {
                try {
                    Mail::send([], [], fn ($m) => $m->to($emailRRHH)->subject($asuntoRRHH)->html($contenidoRRHH));
                } catch (\Exception $e) {
                    $this->error("  Error enviando a RRHH: " . $e->getMessage());
                }
            }

            $this->warn("  Emails de alerta enviados a RRHH");
        }

        ComercialAlerta::create([
            'comercial_id'     => $comercial->id,
            'año'              => $año,
            'mes'              => $mes,
            'tipo'             => 'despido_automatico',
            'email_enviado_at' => now(),
            'plantilla_codigo' => 'despido_aviso',
            'destinatarios'    => array_merge([$comercial->email], $config->emails_notificacion_despido ?? []),
        ]);
    }

    protected function contarMesesConsecutivosSinMinimo($comercialId, $año, $mes): int
    {
        $consecutivos = 0;
        $fechaActual  = Carbon::create($año, $mes, 1);

        for ($i = 0; $i < 12; $i++) {
            $historial = ComercialHistorialObjetivo::where('comercial_id', $comercialId)
                ->where('año', $fechaActual->year)
                ->where('mes', $fechaActual->month)
                ->first();

            if (!$historial || !$historial->alcanzo_todos_minimos_obligatorios) {
                $consecutivos++;
                $fechaActual->subMonth();
            } else {
                break;
            }
        }

        return $consecutivos;
    }

    protected function contarMesesAlternosSinMinimo($comercialId, $año, $mes, $periodoMeses): int
    {
        $fechaFin    = Carbon::create($año, $mes, 1);
        $fechaInicio = $fechaFin->copy()->subMonths($periodoMeses - 1);

        return ComercialHistorialObjetivo::where('comercial_id', $comercialId)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('año', '>', $fechaInicio->year)
                    ->orWhere(function ($q2) use ($fechaInicio) {
                        $q2->where('año', $fechaInicio->year)
                            ->where('mes', '>=', $fechaInicio->month);
                    });
            })
            ->where(function ($q) use ($fechaFin) {
                $q->where('año', '<', $fechaFin->year)
                    ->orWhere(function ($q2) use ($fechaFin) {
                        $q2->where('año', $fechaFin->year)
                            ->where('mes', '<=', $fechaFin->month);
                    });
            })
            ->where('alcanzo_todos_minimos_obligatorios', false)
            ->count();
    }

    protected function reemplazarVariables(string $texto, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $texto = str_replace('{{' . $key . '}}', $value, $texto);
        }

        return $texto;
    }
}
