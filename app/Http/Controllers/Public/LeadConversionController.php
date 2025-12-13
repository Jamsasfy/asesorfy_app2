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
use App\Models\Venta;
use App\Models\Cliente;
use App\Services\FacturacionService; // ✅ Importante
use Illuminate\Support\Facades\Mail;
use App\Mail\ContractSignedMail;
use Illuminate\Support\Facades\Schema;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Invoice;
use Stripe\InvoiceItem;

class LeadConversionController extends Controller
{
    private function getActiveForms(array $blueprint, ?string $legacyFormType = null): array
    {
        $flags = [
            'recurrente'        => false,
            'alta_autonomo'     => false,
            'creacion_sociedad' => false,
            'capitalizacion'    => false,
        ];

        $servicios = $blueprint['servicios'] ?? [];

        if (!empty($servicios) && is_array($servicios)) {
            foreach ($servicios as $servicio) {
                if (!is_array($servicio)) continue;

                $tipo = $servicio['tipo'] ?? null;
                if ($tipo === 'recurrente') $flags['recurrente'] = true;
                if (!empty($servicio['es_alta_autonomo'])) $flags['alta_autonomo'] = true;
                if (!empty($servicio['es_creacion_sociedad'])) $flags['creacion_sociedad'] = true;
                if (!empty($servicio['es_capitalizacion'])) $flags['capitalizacion'] = true;
            }
        }

        if (!$flags['alta_autonomo'] && !$flags['creacion_sociedad'] && !$flags['capitalizacion'] && $legacyFormType) {
            if ($legacyFormType === 'alta_autonomo') $flags['alta_autonomo'] = true;
            if ($legacyFormType === 'creacion_sociedad') $flags['creacion_sociedad'] = true;
            if ($legacyFormType === 'capitalizacion') $flags['capitalizacion'] = true;
        }

        return $flags;
    }

    private function procesarTextosLegales($blueprint, $lead, $form)
    {
        $calle     = DB::table('variables_configuracion')->where('nombre_variable', 'empresa_direccion_calle')->value('valor_variable');
        $cp        = DB::table('variables_configuracion')->where('nombre_variable', 'empresa_direccion_cp')->value('valor_variable');
        $ciudad    = DB::table('variables_configuracion')->where('nombre_variable', 'empresa_direccion_ciudad')->value('valor_variable');
        $provincia = DB::table('variables_configuracion')->where('nombre_variable', 'empresa_direccion_provincia')->value('valor_variable');
        $direccionCompleta = trim("$calle, $cp $ciudad ($provincia)");
        if ($direccionCompleta === ',   ()') $direccionCompleta = 'Dirección no configurada';

        $empresa = [
            'razon'     => DB::table('variables_configuracion')->where('nombre_variable', 'empresa_razon_social')->value('valor_variable') ?? 'ASESORFY S.L.',
            'cif'       => DB::table('variables_configuracion')->where('nombre_variable', 'empresa_cif')->value('valor_variable') ?? 'B-00000000',
            'direccion' => $direccionCompleta,
        ];

        $nombreCompleto = trim(($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? '')) ?: ($lead->nombre ?? 'El Cliente');
        $dniCif         = $form['dni'] ?? $form['cif'] ?? $form['dni_nie'] ?? $lead->dni ?? '—';
        $direccionCli   = trim(($form['direccion'] ?? '') . ' ' . ($form['localidad'] ?? ''));
        if (empty($direccionCli)) $direccionCli = 'Domicilio no especificado';
        $emailCli       = $form['email'] ?? $lead->email ?? '—';

        $services = $blueprint['servicios'] ?? [];
        $htmlTablaCompleta = '';

        $stTable = "width:100%; border-collapse:collapse; margin-top:15px; margin-bottom:15px; font-size:0.9rem; border: 1px solid #bfdbfe; overflow:hidden; border-radius:8px;";
        $stTh    = "background-color:#eff6ff; color:#1e40af; padding:10px; text-align:left; font-weight:bold; border-bottom:2px solid #bfdbfe;";
        $stTd    = "padding:10px; border-bottom:1px solid #e5e7eb; color:#334155; background-color:#fff;";
        $stPrice = "text-align:right; font-weight:bold; color:#0f172a; white-space:nowrap;";
        $stSubHeader = "background-color:#f8fafc; color:#64748b; padding:8px 10px; font-size:0.85rem; font-weight:bold; letter-spacing:0.05em; text-transform:uppercase; border-bottom:1px solid #e2e8f0;";

        if (!empty($services)) {
            $listR = []; $listU = [];
            foreach ($services as $svc) {
                if (($svc['tipo'] ?? '') === 'recurrente') $listR[] = $svc; else $listU[] = $svc;
            }

            $htmlTablaCompleta .= "<table style='{$stTable}'>";
            $htmlTablaCompleta .= "<thead><tr><th style='{$stTh}'>Servicio Contratado</th><th style='{$stTh} text-align:right;'>Importe</th></tr></thead><tbody>";

            if (!empty($listR)) {
                $htmlTablaCompleta .= "<tr><td colspan='2' style='{$stSubHeader}'>Servicios Recurrentes (Mensuales)</td></tr>";
                foreach ($listR as $s) {
                    $precio = number_format((float)($s['precio_base'] ?? 0) * (float)($s['unidades'] ?? 1), 2, ',', '.');
                    $nombre = $s['nombre'] ?? 'Servicio';
                    if(($s['unidades']??1) > 1) $nombre .= " <strong>(x{$s['unidades']})</strong>";
                    $htmlTablaCompleta .= "<tr><td style='{$stTd}'>{$nombre}</td><td style='{$stTd} {$stPrice}'>{$precio} €/mes</td></tr>";
                }
            }
            if (!empty($listU)) {
                $htmlTablaCompleta .= "<tr><td colspan='2' style='{$stSubHeader}'>Servicios de Pago Único (Inicio)</td></tr>";
                foreach ($listU as $s) {
                    $precio = number_format((float)($s['precio_base'] ?? 0) * (float)($s['unidades'] ?? 1), 2, ',', '.');
                    $nombre = $s['nombre'] ?? 'Servicio';
                    if(($s['unidades']??1) > 1) $nombre .= " <strong>(x{$s['unidades']})</strong>";
                    $htmlTablaCompleta .= "<tr><td style='{$stTd}'>{$nombre}</td><td style='{$stTd} {$stPrice}'>{$precio} €</td></tr>";
                }
            }
            $htmlTablaCompleta .= "</tbody></table>";
            $htmlTablaCompleta .= "<div style='text-align:right; font-size:0.75rem; color:#94a3b8; margin-top:-10px; margin-bottom:20px;'>* Impuestos no incluidos</div>";
        } else {
            $htmlTablaCompleta = "<p><em>No se han especificado servicios.</em></p>";
        }

        $replacements = [
            '[AFY_RAZON]'       => $empresa['razon'],
            '[AFY_CIF]'         => $empresa['cif'],
            '[AFY_DIRECCION]'   => $empresa['direccion'],
            '[CLIENTE_NOMBRE]'  => $nombreCompleto,
            '[CLIENTE_DNI]'     => $dniCif,
            '[CLIENTE_DIRECCION]'=> $direccionCli,
            '[CLIENTE_EMAIL]'   => $emailCli,
            '[TABLA_SERVICIOS]' => $htmlTablaCompleta,
        ];

        $dbKeys = ['contrato_cabecera', 'contrato_marco_legal', 'contrato_condiciones_grales', 'servicio_recurrentes', 'servicio_unicos', 'anexo_economico', 'anexo_rgpd_ia'];
        $textosFinales = [];

        foreach ($dbKeys as $key) {
            $raw = DB::table('plantilla_contratos')->where('clave', $key)->value('contenido');
            $textosFinales[$key] = $raw ? str_replace(array_keys($replacements), array_values($replacements), $raw) : "";
        }

        if (!str_contains(implode(' ', $textosFinales), '<table')) {
             $textosFinales['servicio_recurrentes'] = $htmlTablaCompleta . ($textosFinales['servicio_recurrentes'] ?? '');
        }

        return $textosFinales;
    }

    public function show(string $token, Request $request)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');
        $tiposCliente = \App\Models\TipoCliente::all();
        $lead = $link->lead;
        $prefilled = $link->meta['form_data'] ?? [];
        $blueprint = $link->meta['sale_blueprint'] ?? [];
        $legacyFormType = $link->meta['form_type'] ?? 'standard';
        $activeForms = $this->getActiveForms($blueprint, $legacyFormType);

        return view('public.conversion.form', [
            'link' => $link, 'lead' => $lead, 'tipos' => $tiposCliente, 'prefilled' => $prefilled,
            'tieneRecurrente' => $activeForms['recurrente'], 'tieneAltaAutonomo' => $activeForms['alta_autonomo'],
            'tieneCreacionSociedad' => $activeForms['creacion_sociedad'], 'tieneCapitalizacion' => $activeForms['capitalizacion'],
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');
        $blueprint = $link->meta['sale_blueprint'] ?? [];
        $legacyFormType = $link->meta['form_type'] ?? 'standard';
        $activeForms = $this->getActiveForms($blueprint, $legacyFormType);

        $rules = [
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'dni' => 'nullable|string|max:50',
            'cif' => 'nullable|string|max:50',
            'nombre_comercial' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|max:50',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'cp' => 'required|string|max:10',
            'localidad' => 'required|string|max:255',
            'provincia' => 'required|string|max:255',
            'comunidad_autonoma' => 'nullable|string|max:255',
            'tipo_cliente_id' => 'required|integer|exists:tipo_clientes,id',
            'observaciones' => 'nullable|string',
        ];

        if ($activeForms['recurrente']) {
            $rules['cuenta_bancaria_ss'] = 'required|string|max:34';
            $rules['preferencia_pago_recurrente'] = 'required|in:tarjeta,domiciliacion';
        }

        if ($activeForms['alta_autonomo']) {
            $rules = array_merge($rules, [
                'extra_auto_fecha_inicio' => 'required|date',
                'fecha_nacimiento' => 'required|date',
                'seguridad_social' => 'required|string',
                'extra_auto_certificado_digital' => 'required|in:si,no',
                'extra_auto_actividad' => 'required|string',
                'extra_auto_lugar' => 'required|string|in:casa,local,cliente',
                'extra_auto_tarifa_plana' => 'required|string',
                'extra_auto_direccion_local' => 'nullable|string|max:255',
            ]);
        }

        if ($activeForms['creacion_sociedad']) {
            $aportacionTipo = $request->input('extra_sl_aportacion_tipo');

            $slRules = [
                'extra_sl_nombre1' => 'required|string',
                'extra_sl_nombre2' => 'nullable|string',
                'extra_sl_nombre3' => 'nullable|string',
                'extra_sl_nombre4' => 'nullable|string',
                'extra_sl_nombre5' => 'nullable|string',
                'extra_sl_aportacion_tipo' => 'required|in:dineraria,bienes,mixta',
                'extra_sl_actividad' => 'required|string',
                'extra_sl_socios_nombre' => 'required|array|min:1',
                'extra_sl_socios_nombre.*' => 'required|string',
                'extra_sl_socios_dni' => 'nullable|array',
                'extra_sl_socios_dni.*' => 'nullable|string',
                'extra_sl_socios_porcentaje' => 'required|array|min:1',
                'extra_sl_socios_porcentaje.*' => 'required|numeric|min:0|max:100',
                'extra_sl_socios_regimen' => 'nullable|array',
                'extra_sl_socios_regimen.*' => 'nullable|string',
                'extra_sl_tipo_admin' => 'required|string',
                'extra_sl_admin_nombre' => 'required|string',
                'extra_sl_ciudad_firma' => 'required|string',
            ];

            if (in_array($aportacionTipo, ['dineraria', 'mixta'])) {
                $slRules['extra_sl_capital'] = 'required|numeric|min:0';
            } else {
                $slRules['extra_sl_capital'] = 'nullable|numeric|min:0';
            }

            if (in_array($aportacionTipo, ['bienes', 'mixta'])) {
                $slRules['extra_sl_bienes_descripcion'] = 'required|string';
            } else {
                $slRules['extra_sl_bienes_descripcion'] = 'nullable|string';
            }

            $rules = array_merge($rules, $slRules);
        }

        if ($activeForms['capitalizacion']) {
            $rules = array_merge($rules, [
                'extra_cap_forma_juridica' => 'required|string',
                'extra_cap_inversion' => 'required|numeric',
                'extra_cap_solicitado' => 'nullable|numeric',
                'extra_cap_modalidad' => 'required|in:pago_unico,cuotas,mixto,no_lo_se',
                'extra_cap_memoria' => 'required|string',
                'extra_cap_fecha_paro' => 'required|date',
                'extra_cap_prestacion_mensual' => 'required|string',
                'extra_cap_duracion_paro' => 'required|integer|min:1',
                'extra_cap_oficina_sepe' => 'nullable|string|max:255',
            ]);
        }

        $validated = $request->validate($rules);
        $cleanData = $validated;

        $meta = $link->meta ?? [];
        $meta['form_data'] = $cleanData;
        $link->meta = $meta;
        $link->save();

        return redirect()->route('conversion.contract', $link->token);
    }

    public function sign(Request $request, string $token)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        $request->validate([
            'acepto'    => ['accepted'],
            'signature' => ['required', 'string'],
        ]);

        $lead     = $link->lead;
        $signedAt = now();

        try {

            DB::transaction(function () use ($link, $lead, $request, $signedAt) {

                $formData  = $link->meta['form_data'] ?? [];
                $blueprint = $link->meta['sale_blueprint'] ?? [];
                $services  = $blueprint['servicios'] ?? [];

                // 1) Textos legales
                $textos = $this->procesarTextosLegales($blueprint, $lead, $formData);

                // 2) --- Preparar Datos del Cliente ---
                $existingClienteId = $link->meta['existing_cliente_id'] ?? null;
                $existingVentaId   = $link->meta['existing_venta_id'] ?? null; 

                $cliente = $existingClienteId ? Cliente::find($existingClienteId) : null;

                // Extraemos datos básicos
                $nombre    = trim($formData['nombre'] ?? '');
                $apellidos = trim($formData['apellidos'] ?? '');
                $nombreCompletoContacto = trim("$nombre $apellidos") ?: ($lead->nombre ?? '');
                
                $esEmpresa = !empty($formData['cif']); 
                $inputRazonFormulario = $formData['razon_social'] ?? null;

                if ($esEmpresa) {
                    $razonSocialReal = $inputRazonFormulario ?? $nombreCompletoContacto;
                    $nombreComercial = $inputRazonFormulario;
                    $dniCif          = $formData['cif'] ?? null;
                } else {
                    $razonSocialReal = $nombreCompletoContacto;
                    $nombreComercial = $inputRazonFormulario; 
                    $dniCif          = $formData['dni'] ?? null;
                }

                $dataCliente = [
                    'nombre'                           => $nombre,
                    'apellidos'                        => $apellidos,
                    'dni_cif'                          => $dniCif,
                    'razon_social'                     => $razonSocialReal,
                    'nombre_comercial'                 => $nombreComercial,
                    'direccion'                        => $formData['direccion'] ?? null,
                    'codigo_postal'                    => $formData['cp'] ?? null,
                    'localidad'                        => $formData['localidad'] ?? null,
                    'provincia'                        => $formData['provincia'] ?? null,
                    'comunidad_autonoma'               => $formData['comunidad_autonoma'] ?? null,
                    'iban_impuestos'                   => $formData['cuenta_bancaria_ss'] ?? null,
                    'preferencia_pago_recurrente'      => $formData['preferencia_pago_recurrente'] ?? 'tarjeta',
                    'email_contacto'                   => $formData['email']    ?? $lead->email,
                    'telefono_contacto'                => $formData['telefono'] ?? $lead->tfn,
                ];

                if ($cliente) {
                    $cliente->update($dataCliente);
                } else {
                    $dataCliente['tipo_cliente_id'] = $formData['tipo_cliente_id'] ?? 1;
                    $dataCliente['comercial_id']    = $lead->asignado_id;
                    $dataCliente['estado']          = 'activo';
                    $dataCliente['fecha_alta']      = $signedAt;

                    $cliente = Cliente::create($dataCliente);

                    $lead->cliente_id = $cliente->id;
                    $lead->save();
                }

                // Crear CUSTOMER EN STRIPE
                if (!$cliente->stripe_customer_id) {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    if (app()->isLocal()) \Stripe\Stripe::setVerifySslCerts(false);
                    
                    $stripeData = [
                        'email' => $cliente->email_contacto,
                        'name'  => $cliente->razon_social,
                        'metadata' => [
                            'cliente_id' => $cliente->id,
                            'nombre_comercial' => $cliente->nombre_comercial,
                        ]
                    ];

                    if ($cliente->nombre_comercial && $cliente->nombre_comercial !== $cliente->razon_social) {
                        $stripeData['description'] = $cliente->nombre_comercial;
                    }

                    $stripeCustomer = \Stripe\Customer::create($stripeData);

                    $cliente->stripe_customer_id = $stripeCustomer->id;
                    $cliente->save();
                }

                $meta = $link->meta ?? [];
                $meta['cliente_id'] = $cliente->id;
                $link->meta = $meta;
                $link->save();

                $lead->forceFill([
                    'nombre' => $nombreCompletoContacto,
                    'email'  => $formData['email']    ?? $lead->email,
                    'tfn'    => $formData['telefono'] ?? $lead->tfn,
                ])->save();

                // 3) --- Venta ---
                $venta = null;
                $totalUnico = 0;
                
                foreach ($services as $s) {
                    if (($s['tipo'] ?? '') !== 'recurrente') {
                        $totalUnico += ((float)($s['precio_base'] ?? 0)) * ((int)($s['unidades'] ?? 1));
                    }
                }

                if ($existingVentaId) {
                    $venta = Venta::find($existingVentaId);
                    if ($venta) {
                        $metodoPago = $venta->pago_inicial_metodo;
                        if (!$metodoPago && isset($blueprint['pago_inicial_metodo'])) {
                            $metodoPago = $blueprint['pago_inicial_metodo'];
                        }
                        if ($metodoPago) {
                            $venta->pago_inicial_metodo = $metodoPago;
                        }
                        $venta->cliente_id = $cliente->id;
                        $venta->signed_at  = $signedAt;
                        $venta->save();
                    }

                } else {
                    $venta = Venta::create([
                        'cliente_id'          => $cliente->id,
                        'lead_id'             => $lead->id,
                        'user_id'             => $lead->asignado_id,
                        'fecha_venta'         => $signedAt,
                        'importe_total'       => $totalUnico,
                        'estado'              => \App\Enums\VentaEstadoEnum::PENDIENTE,
                        'signed_at'           => $signedAt,
                        'pago_inicial_metodo' => $blueprint['pago_inicial_metodo'] ?? 'stripe',
                    ]);

                    foreach ($services as $s) {
                        
                        $servicioModel = null;
                        $idServicio = $s['id'] ?? ($s['servicio_id'] ?? null);
                        
                        if ($idServicio) {
                            $servicioModel = \App\Models\Servicio::find($idServicio);
                        } elseif (!empty($s['nombre'])) {
                            $servicioModel = \App\Models\Servicio::whereRaw('LOWER(nombre) = ?', [strtolower($s['nombre'])])->first();
                        }
                        
                        $finalServiceId = $servicioModel?->id ?? $idServicio;
                        
                        $precioBase = (float) ($s['precio_base'] ?? 0);
                        $unidades   = (float) ($s['unidades'] ?? 1);
                        $subtotal   = $precioBase * $unidades;

                        $venta->items()->create([
                            'servicio_id'          => $finalServiceId,
                            'nombre_personalizado' => $s['nombre'] ?? ($servicioModel->nombre ?? 'Servicio'),
                            'cantidad'             => $unidades,
                            'precio_unitario'      => $precioBase,
                            'subtotal'             => $subtotal,
                            'subtotal_aplicado'    => $subtotal,
                            'requiere_proyecto'    => $servicioModel ? $servicioModel->requiere_proyecto_activacion : false,
                        ]);
                    }

                    $meta = $link->meta ?? [];
                    $meta['existing_venta_id'] = $venta->id;
                    $link->meta = $meta;
                    $link->save();
                }

                $venta->refresh(); 

                // ---------------------------------------------------------
                // 🔥 ACTIVACIÓN AUTOMÁTICA CONDICIONAL
                // ---------------------------------------------------------
                
                $totalCobroInicial = 0;
                if ($venta->items) {
                     foreach ($venta->items as $item) {
                         if (!$item->servicio) continue;
                         $tipo = $item->servicio->tipo instanceof \BackedEnum 
                                 ? $item->servicio->tipo->value 
                                 : $item->servicio->tipo;

                         if ($tipo === 'unico') {
                             $totalCobroInicial += $item->subtotal_aplicado;
                         }
                     }
                }

                $tieneMetodoPago = false;
                if ($cliente->stripe_customer_id) {
                    try {
                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                        if (app()->isLocal()) \Stripe\Stripe::setVerifySslCerts(false);
                        
                        $cus = \Stripe\Customer::retrieve([
                            'id' => $cliente->stripe_customer_id,
                            'expand' => ['invoice_settings.default_payment_method']
                        ]);
                        $tieneMetodoPago = !empty($cus->invoice_settings->default_payment_method);
                    } catch (\Exception $e) {
                    }
                }

                if ($totalCobroInicial <= 0 && $tieneMetodoPago) {
                    $venta->procesarCobroInicial(
                        fechaPago: now(), 
                        metodoPago: 'suscripcion_directa', 
                        paymentIntentId: null, 
                        extraData: $formData 
                    );
                } else {
                    \Log::info("Venta #{$venta->id} creada PENDIENTE. Esperando método de pago en 'finished'.");
                }

                // 4) --- Generar PDF ---
                $pdf = Pdf::loadView('public.conversion.contract.master', [
                    'lead'             => $lead,
                    'form'             => $formData,
                    'blueprint'        => $blueprint,
                    'link'             => $link,
                    'textos'           => $textos,
                    'servicesSummary'  => $services,
                    'signedAt'         => $signedAt,
                    'signatureDataUri' => $request->input('signature'),
                    'clientIp'         => $request->ip(),
                    'isPdf'            => true,
                ])->setPaper('a4');

                $fileName = 'contracts/contrato_' . $link->token . '_' . $signedAt->format('Ymd_His') . '.pdf';
                Storage::disk('public')->put($fileName, $pdf->output());

                $meta = $link->meta ?? [];
                $meta['pdf'] = $fileName;
                $link->meta  = $meta;
                $link->save();

                // 5) Email contrato
                try {
                    $absolutePdfPath = storage_path('app/public/' . $fileName);
                    Mail::to($cliente->email_contacto)->send(
                        new \App\Mail\ContractSignedMail($lead, $absolutePdfPath)
                    );
                    \App\Models\LeadAutoEmailLog::create([
                        'lead_id'             => $lead->id,
                        'estado'              => 'firmado',
                        'intento'             => 1,
                        'template_identifier' => 'contract_signed',
                        'subject'             => 'Aquí tienes tu contrato firmado con AsesorFy',
                        'body_preview'        => 'Contrato firmado adjunto enviado al cliente.',
                        'scheduled_at'        => now(),
                        'sent_at'             => now(),
                        'status'              => 'sent',
                        'mail_driver'         => config('mail.default'),
                        'triggered_by_user_id'=> 9999,
                        'trigger_source'      => 'firma_contrato',
                    ]);
                } catch (\Throwable $e) {
                    \Log::error("ERROR enviando ContractSignedMail: " . $e->getMessage());
                }

                $lead->estado = LeadEstadoEnum::CONVERTIDO_FIRMADO;
                $lead->save();

                $link->used_at = now();
                $link->save();

            });

        } catch (\Throwable $e) {
            Log::error('CRITICAL ERROR en sign(): '.$e->getMessage().' en línea '.$e->getLine(), ['token'=>$token]);
            throw new HttpException(500, 'Ha ocurrido un error al firmar. Inténtelo de nuevo.');
        }

        return redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    public function contract(string $token, Request $request)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');
        if (!$link) abort(404, 'Enlace no válido.');

        $lead     = $link->lead;
        $formData  = $link->meta['form_data'] ?? [];
        $blueprint = $link->meta['sale_blueprint'] ?? [];
        $services  = $blueprint['servicios'] ?? [];
        $textos    = $this->procesarTextosLegales($blueprint, $lead, $formData);

        return view('public.conversion.contract', [
            'lead'            => $lead,
            'form'            => $formData,
            'blueprint'       => $blueprint,
            'servicesSummary' => $services,
            'textos'          => $textos,
            'link'            => $link,
            'isPdf'           => false,
        ]);
    }

    // =========================================================================
    // 🔥 FINISHED: LÓGICA DE AUTOCOBRO INICIAL Y GESTIÓN DE PROYECTOS 🔥
    // =========================================================================
    public function finished(string $token, Request $request)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');
        if (!$link) abort(404);

        $lead  = $link->lead;
        $form  = $link->meta['form_data'] ?? [];
        $venta = null;

        if (!empty($link->meta['existing_venta_id'])) {
            $venta = Venta::with('items.servicio', 'cliente', 'proyectos')->find($link->meta['existing_venta_id']);
        }

        $cliente = $venta?->cliente;

        // 1. ANÁLISIS DE LA VENTA
        $itemsUnicos = collect();
        $itemsRecurrentes = collect();
        $esperaProyecto = false;

        if ($venta) {
            $itemsUnicos = $venta->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'unico');
            $itemsRecurrentes = $venta->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

            $esperaProyecto = $venta->items->contains(function ($item) {
                if (!$item->servicio) return false;
                return $item->servicio->es_editable
                    ? $item->requiere_proyecto
                    : $item->servicio->requiere_proyecto_activacion;
            });
        }

        // 2. IVA Y TOTALES
        $cpCliente   = $form['cp'] ?? ($cliente->codigo_postal ?? '');
        $provCliente = $form['provincia'] ?? ($cliente->provincia ?? '');
        $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
        $factorIva     = 1 + ($porcentajeIva / 100);

        $importeSinIvaInicial = $itemsUnicos->sum(fn ($i) => (float) $i->subtotal_aplicado);
        $importePagoInicial   = round($importeSinIvaInicial * $factorIva, 2);

        $precioMensualSinIva = $itemsRecurrentes->sum(fn ($i) => (float) $i->subtotal_aplicado);
        $totalRecurrenteConIva = round($precioMensualSinIva * $factorIva, 2);

        // 3. ESTADO PAGOS
        $tieneRecurrente = $itemsRecurrentes->isNotEmpty();
        $tienePagoInicialPendiente = ($importePagoInicial > 0) && !$venta?->tienePagoInicialCompletado();
        $preferencia = $form['preferencia_pago_recurrente'] ?? $cliente?->preferencia_pago_recurrente ?? 'tarjeta';
        
        $pagoRecurrenteCompletado = false;
        $cardInfo = null;

        // 4. VERIFICACIÓN DE STRIPE
        if (!$tieneRecurrente) {
            $pagoRecurrenteCompletado = true;
        } elseif ($cliente && $cliente->stripe_customer_id) {
            Stripe::setApiKey(config('services.stripe.secret'));
            if (app()->isLocal()) Stripe::setVerifySslCerts(false);

            try {
                $customer = Customer::retrieve([
                    'id'     => $cliente->stripe_customer_id,
                    'expand' => ['invoice_settings.default_payment_method'],
                ]);
                $defaultPM = $customer->invoice_settings->default_payment_method ?? null;

                if ($defaultPM) {
                    $pagoRecurrenteCompletado = true;
                    if (isset($defaultPM->card)) {
                        $cardInfo = ['type' => 'card', 'brand' => ucfirst($defaultPM->card->brand), 'last4' => $defaultPM->card->last4];
                    } elseif (isset($defaultPM->sepa_debit)) {
                        $cardInfo = ['type' => 'sepa', 'last4' => $defaultPM->sepa_debit->last4];
                    }
                } else {
                    return $this->redirectStripeSetup($preferencia === 'domiciliacion' ? 'domiciliacion' : 'tarjeta', $token);
                }
            } catch (\Throwable $e) {
                Log::error('Stripe finished error: ' . $e->getMessage());
            }
        } elseif ($cliente && !$cliente->stripe_customer_id) {
            return $this->redirectStripeSetup($preferencia, $token);
        }

        // =====================================================================
        // 🔥 AUTO-COBRO AL VOLVER DEL SETUP (Aquí está la mejora)
        // =====================================================================
        if (session('payment_setup_success') && $venta) {
            Log::info("💳 Método de pago guardado. Procesando cobros pendientes para Venta #{$venta->id}");

            // A) AUTO-COBRO PAGO INICIAL (Si existe y está pendiente)
            if ($tienePagoInicialPendiente) {
                try {
                    Log::info("💰 Intentando cobrar Pago Inicial ({$importePagoInicial}€) automáticamente...");
                    
                    // 1. Crear InvoiceItems para los servicios únicos
                    foreach ($itemsUnicos as $item) {
                        $importeItemBruto = round($item->subtotal_aplicado * $factorIva, 2);
                        if ($importeItemBruto <= 0) continue;

                        InvoiceItem::create([
                            'customer'    => $cliente->stripe_customer_id,
                            'amount'      => (int)($importeItemBruto * 100),
                            'currency'    => 'eur',
                            'description' => $item->nombre_personalizado ?? $item->servicio->nombre,
                            'metadata'    => ['venta_id' => $venta->id, 'tipo' => 'pago_unico'],
                        ]);
                    }

                  // 2. Crear y FORZAR PAGO DE LA INVOICE
                    $invoice = Invoice::create([
                        'customer'     => $cliente->stripe_customer_id,
                        'auto_advance' => true, // Importante para que Stripe sepa que debe cobrarse
                    ]);
                    
                    // Finalizamos para que deje de ser "draft"
                    $invoice->finalizeInvoice();

                    // 🔥 CLAVE: Forzamos el intento de pago síncrono AHORA MISMO.
                    // Si la tarjeta falla o pide 3DSecure complejo, esto lanzará excepción
                    // y no marcaremos la factura local como pagada erróneamente.
                    $invoice->pay(); 
                    
                    // Refrescamos para asegurar estado (opcional pero recomendado)
                    $invoice = Invoice::retrieve($invoice->id);

                    if ($invoice->status !== 'paid') {
                        throw new \Exception("El cobro no se completó inmediatamente. Estado: " . $invoice->status);
                    }
                    
                    Log::info("✅ Pago Inicial cobrado en Stripe (Invoice {$invoice->id})");

                    // 3. Generar Factura Local PAGADA
                    FacturacionService::generarFacturaInicial($venta, now(), 'stripe');
                    
                    // Actualizamos flag para la vista
                    $tienePagoInicialPendiente = false; 

                } catch (\Exception $e) {
                    Log::error("❌ Error en auto-cobro inicial: " . $e->getMessage());
                    // Si falla, el usuario verá el botón de "Reintentar Pago" en la vista, no es crítico.
                }
            }

            // B) PROCESAR SUSCRIPCIÓN (Respetando Proyectos)
            try {
                // Aquí dentro ya se comprueba si 'requiereProyecto'. 
                // Si lo requiere, NO activa Stripe y solo deja la suscripción en 'pendiente_activacion'.
                $venta->procesarCobroInicial(now(), 'stripe_automatico');
                $venta->refresh();
            } catch (\Exception $e) {
                Log::error("Error procesando suscripción tras setup: " . $e->getMessage());
            }
        }

        // =========================
        // PREPARACIÓN FINAL DE VISTA
        // =========================
        
        // Recálculo de prorrata visual (Solo si no hay proyecto)
        $prorrateo = null;
        if ($itemsRecurrentes->isNotEmpty() && !$esperaProyecto) {
            $hoy = now();
            $diasMes = $hoy->daysInMonth;
            $diasRestantes = ($diasMes - $hoy->day) + 1;
            
            // Recálculo rápido
            $totalConIva = round($itemsRecurrentes->sum(fn($i)=>(float)$i->subtotal_aplicado) * $factorIva, 2);
            $totalSinIva = $itemsRecurrentes->sum(fn($i)=>(float)$i->subtotal_aplicado);
            
            $importeProrrateadoConIva = round(($totalConIva / $diasMes) * $diasRestantes, 2);
            $importeProrrateadoSinIva = round(($totalSinIva / $diasMes) * $diasRestantes, 2);

            $prorrateo = [
                'mes_actual'      => $hoy->locale('es')->monthName,
                'dias_restantes'  => $diasRestantes,
                'importe'         => number_format($importeProrrateadoConIva, 2, ',', '.'),
                'importe_sin_iva' => number_format($importeProrrateadoSinIva, 2, ',', '.'),
                'es_gratis'       => $importeProrrateadoConIva <= 0,
            ];
        }

        $nombresRecurrentes = $itemsRecurrentes->map(function ($item) {
            $nombre = $item->nombre_personalizado ?? $item->servicio->nombre ?? 'Servicio';
            return ($item->cantidad > 1) ? "$nombre (x{$item->cantidad})" : $nombre;
        });
        $nombreServicioRecurrente = $nombresRecurrentes->join(' + ') ?: 'Suscripción mensual';

        $pdfUrl = !empty($link->meta['pdf']) ? Storage::disk('public')->url($link->meta['pdf']) : null;

        return view('public.conversion.finished', [
            'lead'                     => $lead,
            'link'                     => $link,
            'form'                     => $form,
            'venta'                    => $venta,
            'importePagoInicial'       => $importePagoInicial,
            'pagoRecurrenteCompletado' => $pagoRecurrenteCompletado,
            'tieneRecurrente'          => $tieneRecurrente,
            'tienePagoInicialPendiente'=> $tienePagoInicialPendiente, // Si el auto-cobro funciona, esto será false
            'totalRecurrenteMensual'   => $totalRecurrenteConIva,
            'importeSinIvaRecurrente'  => $precioMensualSinIva,
            'nombreServicioRecurrente' => $nombreServicioRecurrente,
            'prorrateo'                => $prorrateo,
            'porcentajeIva'            => $porcentajeIva,
            'cardInfo'                 => $cardInfo,
            'pdfUrl'                   => $pdfUrl,
            'esperaProyecto'           => $esperaProyecto, // 🔥 Variable necesaria en la vista
        ]);
    }

    private function redirectStripeSetup($preferencia, $token)
    {
        $route = $preferencia === 'domiciliacion' ? 'stripe.setup-sepa' : 'stripe.setup-card';
        return redirect()->route($route, ['token' => $token]);
    }
}