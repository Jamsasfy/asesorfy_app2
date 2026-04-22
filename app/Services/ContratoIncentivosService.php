<?php

namespace App\Services;

use App\Models\User;
use App\Models\ComercialContratoIncentivo;
use App\Models\ConfiguracionComisiones;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContratoIncentivosService
{
    /**
     * Genera un contrato de incentivos (base o anexo)
     */
    public function generarContrato(User $comercial, string $tipo = 'base'): ComercialContratoIncentivo
    {
        if (!$comercial->hasRole('comercial')) {
            throw new \Exception('El usuario no tiene rol de comercial');
        }

        $reglasActuales = $comercial->asignacionesReglas()
            ->where('activa', true)
            ->with('regla')
            ->get();

        if ($reglasActuales->isEmpty()) {
            throw new \Exception('El comercial no tiene reglas asignadas');
        }

        $reglasSnapshot = $reglasActuales->map(function ($asignacion) {
            return [
                'id'           => $asignacion->regla_id,
                'nombre'       => $asignacion->regla->nombre,
                'minimo'       => $asignacion->regla->minimo_mensual,
                'porcentaje'   => $asignacion->regla->porcentaje_comision,
                'penalizacion' => $asignacion->regla->penalizacion_baja_antes_meses,
                'es_obligatoria' => $asignacion->es_obligatoria,
                'activa'       => $asignacion->activa,
            ];
        })->toArray();

        $config = ConfiguracionComisiones::first();
        if (!$config) {
            throw new \Exception('No existe configuración de comisiones');
        }

        $configSnapshot = [
            'meses_consecutivos_despido' => $config->meses_consecutivos_despido,
            'meses_alternos_despido'     => $config->meses_alternos_despido,
            'periodo_meses_alternos'     => $config->periodo_meses_alternos,
        ];

        $contratoBaseId = null;
        if ($tipo === 'anexo') {
            $contratoBase = $comercial->contratoBaseFirmado();
            if (!$contratoBase) {
                throw new \Exception('No existe contrato base firmado para generar anexo');
            }
            $contratoBaseId = $contratoBase->id;
        }

        $contrato = ComercialContratoIncentivo::create([
            'comercial_id'    => $comercial->id,
            'tipo'            => $tipo,
            'contrato_base_id' => $contratoBaseId,
            'reglas_snapshot' => $reglasSnapshot,
            'config_snapshot' => $configSnapshot,
            'fecha_envio'     => now(),
            'hash_documento'  => 'temporal',
        ]);

        $hash = $this->generarPdfConHash($contrato);
        $contrato->update(['hash_documento' => $hash]);

        $contrato = $contrato->fresh();

        // Enviar email al comercial con PDF adjunto y link de firma
        try {
            \Illuminate\Support\Facades\Mail::to($comercial->email)
                ->send(new \App\Mail\ContratoIncentivosMail($contrato));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error enviando email de contrato ID {$contrato->id}: " . $e->getMessage());
        }

        return $contrato;
    }

    /**
     * Genera el PDF con hash SHA-256 (doble generación)
     */
    private function generarPdfConHash(ComercialContratoIncentivo $contrato): string
    {
        $comercial = $contrato->comercial;
        $comercial->load('trabajador');

        $config          = (object) $contrato->config_snapshot;
        $reglas          = $contrato->reglas_snapshot;
        $tieneObligatorias = collect($reglas)->contains('es_obligatoria', true);
        $contratoBase    = $contrato->tipo === 'anexo' ? $contrato->contratoBase : null;

        $datos = [
            'comercial'        => $comercial,
            'tipo'             => $contrato->tipo,
            'reglas'           => $reglas,
            'tieneObligatorias' => $tieneObligatorias,
            'config'           => $config,
            'contratoBase'     => $contratoBase,
            'firmado'          => false,
            'fecha_firma'      => null,
            'ip_firma'         => null,
            'firma_imagen'     => null,
            'hash'             => 'CALCULANDO...',
            'empresaDatos'     => $this->obtenerDatosEmpresa(),
        ];

        // Primera generación para calcular hash
        $pdfContent = Pdf::loadView('pdfs.contrato-incentivos', $datos)->output();
        $hash = hash('sha256', $pdfContent);

        // Segunda generación con hash real
        $datos['hash'] = $hash;
        $pdf = Pdf::loadView('pdfs.contrato-incentivos', $datos);

        $filename  = $this->getNombreArchivo($contrato, false);
        $directory = dirname($filename);
        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }
        Storage::disk('local')->put($filename, $pdf->output());

        return $hash;
    }

    /**
     * Firma el contrato digitalmente
     */
    public function firmarContrato(
        ComercialContratoIncentivo $contrato,
        string $firmaBase64,
        string $ip,
        string $userAgent
    ): void {
        if ($contrato->estaFirmado()) {
            throw new \Exception('El contrato ya está firmado');
        }

        $contrato->update([
            'fecha_firma'       => now(),
            'ip_firma'          => $ip,
            'user_agent_firma'  => $userAgent,
        ]);

        $this->regenerarPdfFirmado($contrato, $firmaBase64);
    }

    /**
     * Regenera el PDF con la firma digital
     */
    private function regenerarPdfFirmado(ComercialContratoIncentivo $contrato, string $firmaBase64): void
    {
        $comercial = $contrato->comercial;
        $comercial->load('trabajador');

        $config          = (object) $contrato->config_snapshot;
        $reglas          = $contrato->reglas_snapshot;
        $tieneObligatorias = collect($reglas)->contains('es_obligatoria', true);
        $contratoBase    = $contrato->tipo === 'anexo' ? $contrato->contratoBase : null;

        $datos = [
            'comercial'        => $comercial,
            'tipo'             => $contrato->tipo,
            'reglas'           => $reglas,
            'tieneObligatorias' => $tieneObligatorias,
            'config'           => $config,
            'contratoBase'     => $contratoBase,
            'firmado'          => true,
            'fecha_firma'      => $contrato->fecha_firma,
            'ip_firma'         => $contrato->ip_firma,
            'firma_imagen'     => $firmaBase64,
            'hash'             => $contrato->hash_documento,
            'empresaDatos'     => $this->obtenerDatosEmpresa(),
        ];

        $pdf = Pdf::loadView('pdfs.contrato-incentivos', $datos);

        $filename  = $this->getNombreArchivo($contrato, true);
        $directory = dirname($filename);
        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }
        Storage::disk('local')->put($filename, $pdf->output());

        $contrato->update(['pdf_path' => $filename]);

        $this->eliminarPdfSinFirmar($contrato);
    }

    /**
     * Elimina el PDF sin firmar después de generar el firmado
     */
    private function eliminarPdfSinFirmar(ComercialContratoIncentivo $contrato): void
    {
        try {
            $files = Storage::disk('local')->files('contratos-incentivos');

            foreach ($files as $file) {
                if (str_contains($file, 'sin-firmar') && str_contains($file, "-{$contrato->id}.pdf")) {
                    Storage::disk('local')->delete($file);
                    \Illuminate\Support\Facades\Log::info("PDF sin firmar eliminado: {$file}");
                    break;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo eliminar PDF sin firmar: " . $e->getMessage());
        }
    }

    /**
     * Obtiene los datos de la empresa desde VariableConfiguracion
     */
    private function obtenerDatosEmpresa(): array
    {
        $vars = \App\Models\VariableConfiguracion::whereIn('nombre_variable', [
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
     * Genera nombre de archivo para el PDF
     */
    private function getNombreArchivo(ComercialContratoIncentivo $contrato, bool $firmado): string
    {
        $comercialSlug = Str::slug($contrato->comercial->name);
        $tipoContrato  = $contrato->tipo === 'anexo' ? 'anexo' : 'base';
        $estado        = $firmado ? 'firmado' : 'sin-firmar';
        $fecha         = now()->format('Y-m-d');

        return "contratos-incentivos/{$comercialSlug}-{$tipoContrato}-{$estado}-{$fecha}-{$contrato->id}.pdf";
    }
}
