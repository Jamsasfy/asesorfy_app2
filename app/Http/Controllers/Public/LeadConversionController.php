<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LeadConversionLink;
use App\Enums\LeadEstadoEnum;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Servicio;
use App\Rules\ValidIban;


class LeadConversionController extends Controller
{
    /**
     * GET /conversion/{token}
     * Muestra el formulario público (flujo AUTOMÁTICO).
     */
    public function show(string $token, Request $request)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        $tiposCliente = \App\Models\TipoCliente::all(); 
        
        // RECUPERAR DATOS PRE-RELLENADOS (Si existen)
        // Esto viene del modo manual
        $prefilled = $link->meta['form_data'] ?? [];

        return view('public.conversion.form', [
            'lead'      => $link->lead,
            'link'      => $link,
            'tipos'     => $tiposCliente,
            'prefilled' => $prefilled, // <--- Pasamos esta variable nueva
        ]);
    }

  /**
     * POST /conversion/{token}
     * Guarda los datos del formulario (Comunes + Extras)
     */
    public function submit(Request $request, string $token): RedirectResponse
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        // 1. Reglas de Validación Base (Datos Cliente)
        $rules = [
            'nombre'             => 'required|string|max:255',
            'apellidos'          => 'required|string|max:255',
            'dni'                => 'required|string|max:50',
            'email'              => 'required|email|max:255',
            'telefono'           => 'required|string|max:50',
            'direccion'          => 'required|string|max:255',
            'cp'                 => 'required|string|max:10',
            'localidad'          => 'required|string|max:255',
            'provincia'          => 'required|string|max:255',
            'cuenta_bancaria_ss' => 'required|string',
            'fecha_nacimiento'   => 'required|date',   // <--- AÑADIR ESTA LÍNEA
            'seguridad_social'   => 'required|string', // <--- AÑADIR ESTA LÍNEA
            'tipo_cliente_id'    => 'required|exists:tipo_clientes,id', // Vital para el Select
            'cuenta_bancaria_ss' => ['required', 'string', new ValidIban], // <--- AQUI LA REGLA MAESTRA
        ];

        // 2. Reglas Condicionales (Solo si el campo viene en el request)
        // Esto permite que la validación funcione dinámicamente según el bloque visible
        
  // Bloque Creación SL (Validación estricta de 5 nombres)
        if ($request->has('extra_sl_capital')) {
             $rules['extra_sl_capital']    = 'required|numeric|min:3000';
             $rules['extra_sl_actividad']  = 'required|string';
             $rules['extra_sl_socios']     = 'required|string';
             $rules['extra_sl_tipo_admin'] = 'required|string';
             $rules['extra_sl_admin_nombre'] = 'required|string';
             $rules['extra_sl_ciudad_firma'] = 'required|string';

             // LOS 5 NOMBRES OBLIGATORIOS
             $rules['extra_sl_nombre1'] = 'required|string';
             $rules['extra_sl_nombre2'] = 'required|string';
             $rules['extra_sl_nombre3'] = 'required|string';
             $rules['extra_sl_nombre4'] = 'required|string';
             $rules['extra_sl_nombre5'] = 'required|string';
        }
      // Bloque Capitalización
        // Usamos 'extra_cap_forma_juridica' como detector porque es un select obligatorio
        if ($request->has('extra_cap_forma_juridica')) {
             $rules['extra_cap_forma_juridica'] = 'required|string';
             $rules['extra_cap_modalidad']      = 'required|string';
             
             // Nuevos campos de situación de desempleo (Obligatorios)
             $rules['extra_cap_fecha_paro']         = 'required|date';
             $rules['extra_cap_prestacion_mensual'] = 'required|string';
             $rules['extra_cap_duracion_paro']      = 'required|string';
             
             // NOTA: 'extra_cap_inversion', 'extra_cap_solicitado' y 'extra_cap_memoria' 
             // son opcionales, por lo que no les ponemos regla 'required'.
        }
        // Bloque Autónomo
        if ($request->has('extra_auto_fecha_inicio')) {
             $rules['extra_auto_actividad'] = 'required|string';
        }

        // 3. Ejecutar validación
        $request->validate($rules);

        // 4. Guardar TODO en el meta
        // Usamos except('_token') para guardar tanto los campos base como los extras
        $todosLosDatos = $request->except(['_token']);
        
        $meta = $link->meta ?? [];
        $meta['form_data'] = $todosLosDatos; // Guardamos el volcado completo
        
        $link->meta = $meta;
        $link->save();

        // 5. Actualizar Lead a "Espera Firma" si no lo estaba
        $lead = $link->lead;
        if ($lead && $lead->estado !== LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA) {
            $lead->estado = LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA;
            $lead->save();
        }

        return redirect()->route('conversion.contract', ['token' => $token]);
    }
   /**
     * GET /conversion/{token}/contrato
     * Muestra el contrato en HTML scrollable (Dinámico).
     */
  public function contract(string $token, Request $request)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        if (! $link) {
            abort(410, 'Enlace no disponible.');
        }

        $lead = $link->lead;
        $form = $link->meta['form_data'] ?? [];
        $blueprint = $link->meta['sale_blueprint'] ?? [];

        // 1. Calcular nombre
        $nombreCompleto = trim(($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? ''));
        if (empty($nombreCompleto)) $nombreCompleto = $lead->nombre;

        // 2. Datos para plantilla
        $datosParaPlantilla = [
            'nombre_completo' => $nombreCompleto,
            'dni'             => $form['dni'] ?? $lead->dni ?? '—',
            'email'           => $form['email'] ?? $lead->email,
            'direccion'       => $form['direccion'] ?? '',
            'localidad'       => $form['localidad'] ?? '',
            'iban'            => $form['cuenta_bancaria_ss'] ?? '—',
            'blueprint'       => $blueprint,
        ];

        // 3. Detectar anexos
        $serviciosCol = collect($blueprint['servicios'] ?? []);
        $tieneRecurrentes = $serviciosCol->contains('tipo', 'recurrente');
        $tieneUnicos      = $serviciosCol->contains('tipo', 'unico');

        // 4. Procesar plantillas
        $plantillasDb = \App\Models\PlantillaContrato::where('activo', true)->get();
        $textosProcesados = [];

        foreach ($plantillasDb as $tpl) {
            if ($tpl->clave === 'servicio_recurrentes' && !$tieneRecurrentes) continue;
            if ($tpl->clave === 'servicio_unicos' && !$tieneUnicos) continue;

            $textosProcesados[$tpl->clave] = $this->procesarPlantilla($tpl->contenido, $datosParaPlantilla);
        }

        // 5. Resumen para la tarjeta lateral (CORREGIDO)
        $servicesSummary = [];
        if (!empty($blueprint['servicios'])) {
            foreach ($blueprint['servicios'] as $svc) {
                $servicesSummary[] = [
                    // 👇 AQUÍ ESTABA EL ERROR: Usamos ?? para evitar fallo si no existe 'nombre'
                    'nombre'      => $svc['nombre'] ?? 'Servicio', 
                    'tipo'        => $svc['tipo'] ?? 'recurrente',
                    'precio_base' => $svc['precio_base'] ?? 0,
                    'unidades'    => $svc['unidades'] ?? 1,
                ];
            }
        }

        return view('public.conversion.contract', [
            'lead'            => $lead,
            'link'            => $link,
            'form'            => $form,
            'servicesSummary' => $servicesSummary,
            'textos'          => $textosProcesados,
        ]);
    }

public function sign(Request $request, string $token)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        // 1. Validaciones
        $request->validate([
            'acepto'    => ['accepted'],
            'signature' => ['required', 'string'],
        ]);

        $lead = $link->lead;
        $signedAt = now();

        try {
            DB::transaction(function () use ($link, $lead, $request, $signedAt) {

                // --- RECUPERAR DATOS ---
                $formData  = $link->meta['form_data'] ?? [];
                $blueprint = $link->meta['sale_blueprint'] ?? [];
                
                // Detectar modo (Manual vs Automático)
                $existingVentaId = $link->meta['existing_venta_id'] ?? null;
                $existingClienteId = $link->meta['existing_cliente_id'] ?? null;

                $nombreCompleto = trim(($formData['nombre'] ?? '') . ' ' . ($formData['apellidos'] ?? ''));
                if (empty($nombreCompleto)) $nombreCompleto = $lead->nombre;

                // --- 1. GESTIÓN DEL CLIENTE ---
                $cliente = null;

                if ($existingClienteId) {
                    // MODO MANUAL: Actualizar cliente existente
                    $cliente = \App\Models\Cliente::find($existingClienteId);
                    if ($cliente) {
                        $cliente->update([
                            'dni_cif'           => $formData['dni'] ?? $cliente->dni_cif,
                            'direccion'         => $formData['direccion'] ?? $cliente->direccion,
                            'codigo_postal'     => $formData['cp'] ?? $cliente->codigo_postal,
                            'localidad'         => $formData['localidad'] ?? $cliente->localidad,
                            'provincia'         => $formData['provincia'] ?? $cliente->provincia,
                            'comunidad_autonoma'=> $formData['comunidad_autonoma'] ?? $cliente->comunidad_autonoma,
                            'iban_asesorfy'     => $formData['cuenta_bancaria_ss'] ?? $cliente->iban_asesorfy,
                            'email_contacto'    => $formData['email'] ?? $cliente->email_contacto,
                            'telefono_contacto' => $formData['telefono'] ?? $cliente->telefono_contacto,
                            'razon_social'      => $nombreCompleto,
                        ]);
                    }
                } 
                
                if (!$cliente) {
                    // MODO AUTOMÁTICO (o fallback): Crear Cliente Nuevo
                    $cliente = \App\Models\Cliente::create([
                        'tipo_cliente_id'   => $formData['tipo_cliente_id'] ?? 1, 
                        'razon_social'      => $nombreCompleto,
                        'dni_cif'           => $formData['dni'] ?? null,
                        'email_contacto'    => $formData['email'] ?? $lead->email,
                        'telefono_contacto' => $formData['telefono'] ?? $lead->tfn,
                        'direccion'         => $formData['direccion'] ?? null,
                        'codigo_postal'     => $formData['cp'] ?? null,
                        'localidad'         => $formData['localidad'] ?? null,
                        'provincia'         => $formData['provincia'] ?? null,
                        'comunidad_autonoma'=> $formData['comunidad_autonoma'] ?? null,
                        'iban_asesorfy'     => $formData['cuenta_bancaria_ss'] ?? null,
                        'observaciones'     => $formData['observaciones'] ?? null,
                        'comercial_id'      => $lead->asignado_id,
                        'estado'            => 'activo', 
                        'fecha_alta'        => $signedAt,
                    ]);
                    
                    // Vinculación inversa
                    $lead->cliente_id = $cliente->id;
                    $lead->save();
                }

                // 2. Actualizar Lead (Datos contacto básicos)
                $lead->forceFill([
                    'nombre' => $nombreCompleto,
                    'email'  => $formData['email'] ?? $lead->email,
                    'tfn'    => $formData['telefono'] ?? $lead->tfn,
                ])->save();

                // --- 3. GESTIÓN DE LA VENTA ---
                $venta = null;

                if ($existingVentaId) {
                    // === MODO MANUAL ===
                    $venta = \App\Models\Venta::find($existingVentaId);
                    
                    if ($venta) {
                        // A) Marcar como firmada
                        $venta->update(['signed_at' => $signedAt]);

                        // B) Actualizar Proyectos existentes con los datos del formulario
                        if ($venta->proyectos()->exists()) {
                            // Guardamos datos en observaciones como respaldo
                            $infoExtra = "\n\n[DATOS FORMULARIO FIRMA]\n" . json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            $venta->update([
                                'observaciones' => $venta->observaciones . $infoExtra
                            ]);
                        } else {
                            // Si no había proyectos, los creamos ahora volcando los datos
                            $venta->processSaleAfterCreation($formData);
                        }
                        
                        // C) Asegurar Factura (Servicios Únicos)
                        if ($venta->facturas()->count() === 0) {
                            $venta->updateTotal(); 
                            \App\Services\FacturacionService::generarFacturaParaVenta($venta);
                        }
                    }

                } else {
                    // === MODO AUTOMÁTICO ===
                    if (!empty($blueprint['servicios'])) {
                        // A) Crear Venta
                        $venta = \App\Models\Venta::create([
                            'cliente_id'    => $cliente->id,
                            'lead_id'       => $lead->id,
                            'user_id'       => $lead->asignado_id ?? 1,
                            'fecha_venta'   => $signedAt,
                            'importe_total' => 0, 
                            'signed_at'     => $signedAt,
                        ]);

                        // B) Crear Items
                        foreach ($blueprint['servicios'] as $item) {
                            $precioBase = (float) $item['precio_base'];
                            $unidades   = (int) ($item['unidades'] ?? 1);
                            $subtotal   = $precioBase * $unidades;
                            $subtotalConIva = $subtotal * 1.21; // IVA 21%

                            \App\Models\VentaItem::create([
                                'venta_id'                  => $venta->id,
                                'servicio_id'               => $item['servicio_id'],
                                'cantidad'                  => $unidades,
                                'precio_unitario'           => $precioBase,
                                'subtotal'                  => $subtotal,
                                'subtotal_con_iva'          => $subtotalConIva,
                                'precio_unitario_aplicado'  => $precioBase, 
                                'subtotal_aplicado'         => $subtotal,
                                'subtotal_aplicado_con_iva' => $subtotalConIva,
                                'nombre_personalizado'      => $item['nombre'] ?? null,
                                'requiere_proyecto'         => 0,
                            ]);
                        }
                        
                        // C) Lógica de Negocio Completa
                        $venta->refresh(); 
                        $venta->updateTotal(); 
                        $venta->processSaleAfterCreation($formData); // Vuelca datos al proyecto
                        \App\Services\FacturacionService::generarFacturaParaVenta($venta); // Genera factura pendiente
                    }
                }

                // --- 4. FIRMA Y PDF ---
                $signatureDataUri = $request->input('signature');
                $pngBytes = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signatureDataUri));
                $sigFilename = "lead-{$lead->id}-signature-{$signedAt->format('YmdHis')}.png";
                $sigPath = "contracts/signatures/{$sigFilename}";
                Storage::disk('public')->put($sigPath, $pngBytes);

                // Datos para PDF
                $datosParaPlantilla = [
                    'nombre_completo' => $nombreCompleto,
                    'dni'             => $formData['dni'] ?? '—',
                    'email'           => $formData['email'] ?? $lead->email,
                    'direccion'       => $formData['direccion'] ?? '',
                    'localidad'       => $formData['localidad'] ?? '',
                    'iban'            => $formData['cuenta_bancaria_ss'] ?? '—',
                    'blueprint'       => $blueprint,
                ];

                // Filtrar y Procesar Plantillas
                $serviciosCol = collect($blueprint['servicios'] ?? []);
                $tieneRecurrentes = $serviciosCol->contains('tipo', 'recurrente');
                $tieneUnicos      = $serviciosCol->contains('tipo', 'unico');

                $plantillasDb = \App\Models\PlantillaContrato::where('activo', true)->get();
                $textosProcesados = [];

                foreach ($plantillasDb as $tpl) {
                    if ($tpl->clave === 'servicio_recurrentes' && !$tieneRecurrentes) continue;
                    if ($tpl->clave === 'servicio_unicos' && !$tieneUnicos) continue;
                    $textosProcesados[$tpl->clave] = $this->procesarPlantilla($tpl->contenido, $datosParaPlantilla);
                }

                // Generar PDF
                $clientIp = $request->ip();
                $pdf = Pdf::loadView('public.conversion.contract.master', [
                    'textos'           => $textosProcesados,
                    'form'             => $formData,
                    'signatureDataUri' => $signatureDataUri,
                    'signedAt'         => $signedAt,
                    'clientIp'         => $clientIp,
                ])->setPaper('a4')->setOptions(['isRemoteEnabled' => true]);

                $pdfFilename = "Contrato_Servicios_AsesorFy_{$lead->id}.pdf";
                $pdfPath = "contracts/pdfs/{$pdfFilename}";
                Storage::disk('public')->put($pdfPath, $pdf->output());

                // Actualizar Lead
                $lead->update([
                    'contract_pdf_path'  => $pdfPath,
                    'contract_signed_at' => $signedAt,
                    'estado'             => \App\Enums\LeadEstadoEnum::CONVERTIDO_FIRMADO, // Gatillo Boot IA
                ]);

                // Actualizar Link
                $meta = $link->meta ?? [];
                $meta['signature_path']     = $sigPath;
                $meta['client_ip']          = $clientIp;
                $meta['cliente_created_id'] = $cliente->id;
                $meta['venta_created_id']   = $venta ? $venta->id : null;
                
                $link->update(['used_at' => $signedAt, 'meta' => $meta]);

                // --- 5. ENVIO DE EMAIL CONTRATO (Con Botón de Pago) ---
                
                // Buscamos la factura pendiente para pasarla al email
                $facturaEmail = null;
                if ($venta) {
                    $facturaEmail = $venta->facturas()->where('estado', \App\Enums\FacturaEstadoEnum::PENDIENTE_PAGO)->first();
                }

                try {
                    \Illuminate\Support\Facades\Mail::to($formData['email'] ?? $lead->email)
                        ->send(new \App\Mail\ContractSignedMail(
                            $lead, 
                            Storage::disk('public')->path($pdfPath),
                            $facturaEmail // Pasamos la factura
                        ));

                    // Logs y Comentarios
                    $lead->comentarios()->create([
                        'user_id'   => 9999, 
                        'contenido' => "✅ 📄 Contrato firmado. PDF enviado a {$formData['email']}.",
                    ]);

                    \App\Models\LeadAutoEmailLog::create([
                        'lead_id'             => $lead->id,
                        'estado'              => 'convertido_firmado',
                        'intento'             => 1,
                        'template_identifier' => 'contract_signed_pdf',
                        'subject'             => 'Contrato Firmado (Sistema)',
                        'body_preview'        => 'Envío automático del PDF tras firma.',
                        'scheduled_at'        => now(),
                        'sent_at'             => now(),
                        'status'              => 'sent',
                        'mail_driver'         => config('mail.default'),
                        'trigger_source'      => 'system_sign_event',
                    ]);

                } catch (\Exception $e) {
                    Log::error("Error enviando contrato: " . $e->getMessage());
                }
            });

        } catch (\Exception $e) {
            Log::error("Error crítico firma: " . $e->getMessage());
            throw new HttpException(500, 'Error procesando firma.');
        }

        // --- VISTA FINAL ---
        $form = $link->meta['form_data'] ?? [];
        $pdfUrl = Storage::disk('public')->url($lead->contract_pdf_path);
        
        // Recuperamos la factura para la vista web
        $ventaId = $link->meta['venta_created_id'] ?? $link->meta['existing_venta_id'] ?? null;
        $facturaPendiente = null;
        
        if ($ventaId) {
            $venta = \App\Models\Venta::find($ventaId);
            if ($venta) {
                $facturaPendiente = $venta->facturas()
                    ->where('estado', \App\Enums\FacturaEstadoEnum::PENDIENTE_PAGO)
                    ->latest()
                    ->first();
            }
        }

        return view('public.conversion.finished', [
            'lead'    => $lead,
            'pdfUrl'  => $pdfUrl,
            'form'    => $form,
            'factura' => $facturaPendiente, // Pasamos factura a la vista
        ]);
    }
    // ==========================================
    // MÉTODOS AUXILIARES (Privados)
    // ==========================================

    /**
     * Sustituye las variables [CORCHETES] por datos reales.
     */
    private function procesarPlantilla(?string $html, array $datos): string
    {
        if (empty($html)) return '';

        // 1. Cargar Configuración AsesorFy
        $afyConfig = \App\Models\VariableConfiguracion::whereIn('nombre_variable', [
            'empresa_razon_social', 'empresa_cif', 'empresa_direccion_calle',
            'empresa_direccion_ciudad', 'empresa_direccion_provincia', 'empresa_email'
        ])->pluck('valor_variable', 'nombre_variable');

        $direccionAfy = implode(', ', array_filter([
            $afyConfig['empresa_direccion_calle'] ?? null,
            $afyConfig['empresa_direccion_ciudad'] ?? null,
            $afyConfig['empresa_direccion_provincia'] ?? null,
        ]));

        // 2. Mapa de Reemplazos
        $reemplazos = [
            '[AFY_RAZON]'     => $afyConfig['empresa_razon_social'] ?? 'ASESORFY S.L.',
            '[AFY_CIF]'       => $afyConfig['empresa_cif'] ?? '',
            '[AFY_DIRECCION]' => $direccionAfy,
            '[AFY_EMAIL]'     => $afyConfig['empresa_email'] ?? '',

            '[CLIENTE_NOMBRE]'    => $datos['nombre_completo'] ?? '—',
            '[CLIENTE_DNI]'       => $datos['dni'] ?? '—',
            '[CLIENTE_DIRECCION]' => trim(($datos['direccion'] ?? '') . ' ' . ($datos['localidad'] ?? '')),
            '[CLIENTE_EMAIL]'     => $datos['email'] ?? '—',
            '[CLIENTE_IBAN]'      => $datos['iban'] ?? '—',
            
            '[FECHA_ACTUAL]'      => now()->format('d/m/Y'),
        ];

        $html = str_replace(array_keys($reemplazos), array_values($reemplazos), $html);

        if (str_contains($html, '[TABLA_SERVICIOS]')) {
            $tabla = $this->generarTablaServiciosHtml($datos['blueprint'] ?? []);
            $html = str_replace('[TABLA_SERVICIOS]', $tabla, $html);
        }

        return $html;
    }

   /**
     * Genera la tabla HTML de servicios separando Únicos y Recurrentes.
     */
    private function generarTablaServiciosHtml(array $blueprint): string
    {
        $servicios = $blueprint['servicios'] ?? [];
        if (empty($servicios)) return '<p><em>No hay servicios detallados.</em></p>';

        $filas = '';
        $totalUnico = 0;
        $totalRecurrente = 0;

        // 1. Calcular totales primero para saber si hay mix
        foreach ($servicios as $svc) {
            $importe = ($svc['precio_base'] ?? 0) * ($svc['unidades'] ?? 1);
            if (($svc['tipo'] ?? '') === 'recurrente') {
                $totalRecurrente += $importe;
            } else {
                $totalUnico += $importe;
            }
        }

        // 2. Generar filas con lógica inteligente
        foreach ($servicios as $svc) {
            $importe = ($svc['precio_base'] ?? 0) * ($svc['unidades'] ?? 1);
            $unidades = ($svc['unidades'] ?? 1) > 1 ? " (x{$svc['unidades']})" : '';
            $esRecurrente = ($svc['tipo'] ?? '') === 'recurrente';
            $tipoTexto = $esRecurrente ? 'RECURRENTE (Mensual)' : 'ÚNICO (Pago puntual)';
            
            $nombreServicio = $svc['nombre'] ?? 'Servicio Contratado';
            
            // NOTA DE ACTIVACIÓN: Solo si es recurrente Y hay servicios únicos previos (proyectos)
            $notaActivacion = '';
            if ($esRecurrente && $totalUnico > 0) {
                $notaActivacion = '<br><span style="font-size:9px; color:#d97706; font-style:italic;">* Se activará y cobrará tras finalizar los trámites de servicios únicos.</span>';
            }

            $filas .= "
                <tr>
                    <td style='padding: 8px; border-bottom: 1px solid #eee;'>
                        <strong>{$nombreServicio}</strong>{$unidades}<br>
                        <span style='color:#64748b; font-size:10px;'>Modalidad: {$tipoTexto}</span>
                        {$notaActivacion}
                    </td>
                    <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right; vertical-align: top;'>
                        " . number_format($importe, 2, ',', '.') . " €" . ($esRecurrente ? ' / mes' : '') . "
                    </td>
                </tr>
            ";
        }

        // 3. Construir los Totales del Pie (Separados)
        $footerHtml = '';
        
        // A) Si hay pago único (Capitalización, Alta, SL...)
        if ($totalUnico > 0) {
            $footerHtml .= "
                <tr style='background: #f0fdf4;'>
                    <td style='padding: 10px; text-align: right; font-weight: bold; color: #166534;'>TOTAL A PAGAR AL INICIO (SERVICIOS ÚNICOS):</td>
                    <td style='padding: 10px; text-align: right; font-weight: bold; color: #166534;'>
                        " . number_format($totalUnico, 2, ',', '.') . " €
                    </td>
                </tr>";
        }

        // B) Si hay recurrente (Cuota)
        if ($totalRecurrente > 0) {
            $textoRecurrente = ($totalUnico > 0) 
                ? "CUOTA MENSUAL (Se inicia tras trámites):" 
                : "TOTAL CUOTA MENSUAL:";
                
            $footerHtml .= "
                <tr style='background: #eff6ff;'>
                    <td style='padding: 10px; text-align: right; font-weight: bold; color: #1e40af;'>{$textoRecurrente}</td>
                    <td style='padding: 10px; text-align: right; font-weight: bold; color: #1e40af;'>
                        " . number_format($totalRecurrente, 2, ',', '.') . " €/mes
                    </td>
                </tr>";
        }

        return "
            <div style='border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin: 15px 0;'>
                <table style='width: 100%; border-collapse: collapse; font-size: 12px;'>
                    <thead>
                        <tr style='background: #f8fafc; border-bottom: 2px solid #e2e8f0;'>
                            <th style='padding: 8px; text-align: left; color: #475569;'>CONCEPTO</th>
                            <th style='padding: 8px; text-align: right; color: #475569;'>IMPORTE</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$filas}
                        {$footerHtml}
                    </tbody>
                </table>
                <div style='text-align:right; font-size:9px; color:#64748b; margin-top:4px; padding-right: 5px;'>
                    * Impuestos no incluidos (IVA aplicable según normativa vigente).
                </div>
            </div>
        ";
    }
  /**
     * GET /conversion/{token}/gracias
     * Pantalla final segura (accesible vía GET).
     */
    public function thankyou(string $token)
    {
        // 1. Buscamos el link (sin validación de 'usado', porque ya se usó)
        $link = LeadConversionLink::where('token', $token)
            ->with('lead')
            ->firstOrFail();

        $lead = $link->lead;
        $form = $link->meta['form_data'] ?? [];

        $pdfUrl = $lead->contract_pdf_path
            ? Storage::disk('public')->url($lead->contract_pdf_path)
            : null;

        // 2. RECUPERAR FACTURA PENDIENTE (Lógica movida desde sign)
        // Así el botón de pago sigue saliendo aunque refresques la página
        $facturaPendiente = null;
        $ventaId = $link->meta['venta_created_id'] ?? $link->meta['existing_venta_id'] ?? null;
        
        if ($ventaId) {
            $venta = \App\Models\Venta::find($ventaId);
            if ($venta) {
                $facturaPendiente = $venta->facturas()
                    ->where('estado', \App\Enums\FacturaEstadoEnum::PENDIENTE_PAGO)
                    ->latest()
                    ->first();
            }
        }

        return view('public.conversion.finished', [
            'lead'    => $lead,
            'link'    => $link,
            'pdfUrl'  => $pdfUrl,
            'form'    => $form,
            'factura' => $facturaPendiente, // <-- Pasamos la factura a la vista
        ]);
    }


}