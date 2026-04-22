<?php

namespace App\Services;

use App\Models\ComercialHistorialObjetivo;
use App\Models\VariableConfiguracion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class InformeComisionService
{
    /**
     * Genera el PDF del informe mensual de comisiones
     */
    public function generarInforme(ComercialHistorialObjetivo $historial): string
    {
        $comercial = $historial->comercial;
        $fecha     = Carbon::create($historial->año, $historial->mes, 1);

        $ventas         = $this->obtenerVentasDelMes($comercial->id, $historial->año, $historial->mes);
        $desgloseReglas = $historial->datos_adicionales['desglose_reglas'] ?? [];
        $superaMinimos  = $historial->alcanzo_todos_minimos_obligatorios;
        $empresaDatos   = $this->obtenerDatosEmpresa();

        $datos = [
            'comercial'      => $comercial,
            'historial'      => $historial,
            'fecha'          => $fecha,
            'desgloseReglas' => $desgloseReglas,
            'ventas'         => $ventas,
            'superaMinimos'  => $superaMinimos,
            'empresaDatos'   => $empresaDatos,
            'hash'           => 'CALCULANDO...',
        ];

        // Primera generación para calcular hash
        $pdfContent = Pdf::loadView('pdfs.informe-comision-mensual', $datos)->output();
        $hash = hash('sha256', $pdfContent);

        // Segunda generación con hash real
        $datos['hash'] = $hash;
        $pdf = Pdf::loadView('pdfs.informe-comision-mensual', $datos);

        $nombreArchivo = $this->getNombreArchivo($comercial, $fecha, $historial->id);
        $directorio    = 'informes-comisiones';

        if (!Storage::disk('local')->exists($directorio)) {
            Storage::disk('local')->makeDirectory($directorio);
        }

        $rutaCompleta = "{$directorio}/{$nombreArchivo}";
        Storage::disk('local')->put($rutaCompleta, $pdf->output());

        $historial->update([
            'informe_pdf_path'   => $rutaCompleta,
            'informe_hash'       => $hash,
            'informe_generado_at' => now(),
        ]);

        return $rutaCompleta;
    }

    /**
     * Obtiene las ventas del mes para el comercial
     */
    private function obtenerVentasDelMes(int $comercialId, int $año, int $mes)
    {
        return \App\Models\Venta::where('user_id', $comercialId)
            ->whereYear('fecha_venta', $año)
            ->whereMonth('fecha_venta', $mes)
            ->with(['items.servicio', 'cliente'])
            ->orderBy('fecha_venta')
            ->get();
    }

    /**
     * Obtiene datos de la empresa desde variables de configuración
     */
    private function obtenerDatosEmpresa(): array
    {
        $vars = VariableConfiguracion::whereIn('nombre_variable', [
            'empresa_razon_social',
            'empresa_cif',
            'empresa_direccion_calle',
            'empresa_direccion_cp',
            'empresa_direccion_ciudad',
            'empresa_direccion_provincia',
            'empresa_telefono',
            'empresa_email',
            'empresa_web',
        ])->pluck('valor_variable', 'nombre_variable');

        $direccion = trim(
            ($vars['empresa_direccion_calle'] ?? '') . ' - ' .
            ($vars['empresa_direccion_cp'] ?? '') . ' ' .
            ($vars['empresa_direccion_ciudad'] ?? '') . ', ' .
            ($vars['empresa_direccion_provincia'] ?? '')
        );

        return [
            'razon_social' => $vars['empresa_razon_social'] ?? 'AsesorFy S.L.',
            'cif'          => $vars['empresa_cif'] ?? 'B12345678',
            'direccion'    => $direccion,
            'telefono'     => $vars['empresa_telefono'] ?? '956 123 456',
            'email'        => $vars['empresa_email'] ?? 'info@asesorfy.net',
            'web'          => $vars['empresa_web'] ?? 'www.asesorfy.net',
        ];
    }

    /**
     * Genera el nombre del archivo
     */
    private function getNombreArchivo($comercial, Carbon $fecha, int $historialId): string
    {
        $nombreComercial = \Illuminate\Support\Str::slug($comercial->name);

        return "informe-{$nombreComercial}-{$fecha->format('Y-m')}-{$historialId}.pdf";
    }
}
