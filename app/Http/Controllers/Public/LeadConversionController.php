<?php

namespace App\Http\Controllers\Public;

use App\Models\TipoCliente;
use App\Enums\VentaEstadoEnum;
use App\Models\Servicio;
use BackedEnum;
use Exception;
use App\Models\LeadAutoEmailLog;
use Throwable;
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
        $tiposCliente = TipoCliente::all();
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
        // IBAN obligatorio para gestiones (Hacienda/SS/cuota autónomo)
        $rules['cuenta_bancaria_ss'] = 'required|string|max:34';

        // ⚠️ Ya NO preguntamos aquí el método de pago de la cuota
        // $rules['preferencia_pago_recurrente'] = 'required|in:tarjeta,domiciliacion';
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
        'signature' => ['required', 'string'], // ✅ aquí estaba el typo raro
    ]);

    $lead     = $link->lead;
    $signedAt = now();

    try {

        DB::transaction(function () use ($link, $lead, $request, $signedAt) {

            $formData  = $link->meta['form_data'] ?? [];
            $blueprint = $link->meta['sale_blueprint'] ?? [];
            $services  = $blueprint['servicios'] ?? [];

            // ✅ Método recurrente viene del flujo público (pago-recurrente), NO del form
            $recurrenteMetodo = data_get($link->meta, 'recurrente_metodo'); // 'tarjeta'|'domiciliacion'|null

            // ✅ Método pago inicial (fuente: meta > blueprint > stripe)
            $pagoInicialMetodo = data_get($link->meta, 'pago_inicial_metodo')
                ?? data_get($blueprint, 'pago_inicial_metodo')
                ?? 'stripe';

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

            $esEmpresa = ! empty($formData['cif']);
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
                'nombre'                      => $nombre,
                'apellidos'                   => $apellidos,
                'dni_cif'                     => $dniCif,
                'razon_social'                => $razonSocialReal,
                'nombre_comercial'            => $nombreComercial,
                'direccion'                   => $formData['direccion'] ?? null,
                'codigo_postal'               => $formData['cp'] ?? null,
                'localidad'                   => $formData['localidad'] ?? null,
                'provincia'                   => $formData['provincia'] ?? null,
                'comunidad_autonoma'          => $formData['comunidad_autonoma'] ?? null,
                'iban_impuestos'              => $formData['cuenta_bancaria_ss'] ?? null,

                // ✅ ya NO viene del form:
                'preferencia_pago_recurrente' => $recurrenteMetodo ?? ($cliente?->preferencia_pago_recurrente ?? 'tarjeta'),

                'email_contacto'              => $formData['email']    ?? $lead->email,
                'telefono_contacto'           => $formData['telefono'] ?? $lead->tfn,
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

           // ✅ Determinar si realmente necesitamos Stripe en este flujo
                $tieneRecurrenteEnBlueprint = collect($services)->contains(function ($s) {
                    return ($s['tipo'] ?? null) === 'recurrente';
                });

                // Stripe es necesario si:
                // - hay recurrente (habrá suscripción / setup), o
                // - el pago inicial es por Stripe (checkout), o
                // - ya viene elegido método recurrente (setup card/sepa)
                $necesitaStripeCustomer =
                    $tieneRecurrenteEnBlueprint
                    || ($pagoInicialMetodo === 'stripe')
                    || in_array($recurrenteMetodo, ['tarjeta', 'domiciliacion'], true);

                // Crear CUSTOMER EN STRIPE SOLO si hace falta
                if ($necesitaStripeCustomer && ! $cliente->stripe_customer_id) {
                    Stripe::setApiKey(config('services.stripe.secret'));
                    if (app()->isLocal()) Stripe::setVerifySslCerts(false);

                    $stripeData = [
                        'email' => $cliente->email_contacto,
                        'name'  => $cliente->razon_social,
                        'metadata' => [
                            'cliente_id'       => $cliente->id,
                            'nombre_comercial' => $cliente->nombre_comercial,
                        ],
                    ];

                    if ($cliente->nombre_comercial && $cliente->nombre_comercial !== $cliente->razon_social) {
                        $stripeData['description'] = $cliente->nombre_comercial;
                    }

                    $stripeCustomer = Customer::create($stripeData);

                    $cliente->stripe_customer_id = $stripeCustomer->id;
                    $cliente->save();
                }


            // Guardamos cliente_id y pago_inicial_metodo en meta
            $meta = $link->meta ?? [];
            $meta['cliente_id']          = $cliente->id;
            $meta['pago_inicial_metodo'] = $pagoInicialMetodo;
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
                    $totalUnico += ((float) ($s['precio_base'] ?? 0)) * ((int) ($s['unidades'] ?? 1));
                }
            }

            if ($existingVentaId) {
                $venta = Venta::find($existingVentaId);
                if ($venta) {
                    $venta->cliente_id = $cliente->id;
                    $venta->signed_at  = $signedAt;

                    // ✅ CRÍTICO: dejar el método inicial correcto ANTES de enviar el email contrato
                    $venta->pago_inicial_metodo = $pagoInicialMetodo;

                    $venta->save();
                }
            } else {
                $venta = Venta::create([
                    'cliente_id'          => $cliente->id,
                    'lead_id'             => $lead->id,
                    'user_id'             => $lead->asignado_id,
                    'fecha_venta'         => $signedAt,
                    'importe_total'       => $totalUnico,
                    'estado'              => VentaEstadoEnum::PENDIENTE,
                    'signed_at'           => $signedAt,
                    'pago_inicial_metodo' => $pagoInicialMetodo,
                ]);

                foreach ($services as $s) {

                    $servicioModel = null;
                    $idServicio = $s['id'] ?? ($s['servicio_id'] ?? null);

                    if ($idServicio) {
                        $servicioModel = Servicio::find($idServicio);
                    } elseif (! empty($s['nombre'])) {
                        $servicioModel = Servicio::whereRaw('LOWER(nombre) = ?', [strtolower($s['nombre'])])->first();
                    }

                    $finalServiceId = $servicioModel?->id ?? $idServicio;

                    $precioBase = (float) ($s['precio_base'] ?? 0);
                    $unidades   = (float) ($s['unidades'] ?? 1);
                    $subtotal   = $precioBase * $unidades;

                    // ---------------------------------------------------------
                    // ✅ FIX: respetar "requiere_proyecto" del blueprint si es editable
                    // ---------------------------------------------------------
                    $parseBool = static function ($raw, bool $default = false): bool {
                        if ($raw === null) return $default;
                        if (is_bool($raw)) return $raw;

                        // strings tipo "0", "1", "true", "false", "on", "off"
                        $v = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($v !== null) return $v;

                        // números / otros
                        if (is_numeric($raw)) return ((int) $raw) === 1;

                        return (bool) $raw;
                    };

                    $servicioEsEditable = $servicioModel ? (bool) ($servicioModel->es_editable ?? false) : $parseBool($s['es_editable'] ?? null, false);

                    if ($servicioEsEditable) {
                        // 👇 lo que marcó el comercial en Filament (blueprint)
                        $requiereProyecto = $parseBool($s['requiere_proyecto'] ?? null, false);
                    } else {
                        // 👇 no editable: manda la config del servicio
                        $requiereProyecto = $servicioModel ? (bool) ($servicioModel->requiere_proyecto_activacion ?? false) : $parseBool($s['servicio_requiere_proyecto'] ?? null, false);
                    }

                    $venta->items()->create([
                        'servicio_id'          => $finalServiceId,
                        'nombre_personalizado' => $s['nombre'] ?? ($servicioModel->nombre ?? 'Servicio'),
                        'cantidad'             => $unidades,
                        'precio_unitario'      => $precioBase,
                        'subtotal'             => $subtotal,
                        'subtotal_aplicado'    => $subtotal,
                        'requiere_proyecto'    => $requiereProyecto, // ✅ aquí está el fix real
                    ]);
                }

                $meta = $link->meta ?? [];
                $meta['existing_venta_id'] = $venta->id;
                $link->meta = $meta;
                $link->save();
            }

            $venta->refresh();

            // ---------------------------------------------------------
            // 🔥 ACTIVACIÓN AUTOMÁTICA CONDICIONAL (solo caso sin pago inicial)
            // ---------------------------------------------------------
            $totalCobroInicial = 0;
            if ($venta->items) {
                foreach ($venta->items as $item) {
                    if (! $item->servicio) continue;

                    $tipo = $item->servicio->tipo instanceof BackedEnum
                        ? $item->servicio->tipo->value
                        : $item->servicio->tipo;

                    if ($tipo === 'unico') {
                        $totalCobroInicial += (float) $item->subtotal_aplicado;
                    }
                }
            }

            $tieneMetodoPago = false;

            if ($cliente->stripe_customer_id) {
                try {
                    Stripe::setApiKey(config('services.stripe.secret'));
                    if (app()->isLocal()) Stripe::setVerifySslCerts(false);

                    $cus = Customer::retrieve([
                        'id'     => $cliente->stripe_customer_id,
                        'expand' => ['invoice_settings.default_payment_method'],
                    ]);

                    $defaultPM = $cus->invoice_settings->default_payment_method ?? null;

                    if ($recurrenteMetodo === 'tarjeta') {
                        $tieneMetodoPago = (bool) ($defaultPM && isset($defaultPM->card));
                    } elseif ($recurrenteMetodo === 'domiciliacion') {
                        $tieneMetodoPago = (bool) ($defaultPM && isset($defaultPM->sepa_debit));
                    } else {
                        $tieneMetodoPago = false;
                    }
                } catch (Exception $e) {
                    // opcional log
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
                    // Si hay pago único REAL (subtotal_aplicado > 0), NO enviamos aquí
                    // porque el método (transferencia/stripe) se elige después en guardarPagoInicial().
                    $venta->loadMissing('items.servicio');

                    $tieneUnicoConImporte = $venta->items->contains(function ($item) {
                        if (! $item->servicio) return false;
                        return $item->servicio->tipo->value === 'unico'
                            && (float) ($item->subtotal_aplicado ?? 0) > 0;
                    });

                    if (! $tieneUnicoConImporte) {
                        $absolutePdfPath = storage_path('app/public/' . $fileName);

                        Mail::to($cliente->email_contacto)->send(
                            new ContractSignedMail($lead, $absolutePdfPath, $venta->fresh(['items.servicio', 'cliente']))
                        );

                        LeadAutoEmailLog::create([
                            'lead_id'              => $lead->id,
                            'estado'               => 'firmado',
                            'intento'              => 1,
                            'template_identifier'  => 'contract_signed',
                            'subject'              => 'Aquí tienes tu contrato firmado con AsesorFy',
                            'body_preview'         => 'Contrato firmado adjunto enviado al cliente.',
                            'scheduled_at'         => now(),
                            'sent_at'              => now(),
                            'status'               => 'sent',
                            'mail_driver'          => config('mail.default'),
                            'triggered_by_user_id' => 9999,
                            'trigger_source'       => 'firma_contrato',
                        ]);
                    } else {
                        Log::info("⏭️ ContractSignedMail NO se envía en sign(): hay pago único > 0, se enviará en guardarPagoInicial()", [
                            'venta_id' => $venta->id,
                        ]);
                    }

                } catch (Throwable $e) {
                    \Log::error("ERROR enviando ContractSignedMail: " . $e->getMessage());
                }


            $lead->estado = LeadEstadoEnum::CONVERTIDO_FIRMADO;
            $lead->save();

            $link->used_at = now();
            $link->save();
        });

    } catch (Throwable $e) {
        Log::error('CRITICAL ERROR en sign(): ' . $e->getMessage() . ' en línea ' . $e->getLine(), ['token' => $token]);
        throw new HttpException(500, 'Ha ocurrido un error al firmar. Inténtelo de nuevo.');
    }



        $link->refresh(); // ✅ por seguridad, por si meta cambió en la transacción

        $venta = null;
        if (! empty($link->meta['existing_venta_id'])) {
            $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
        }

        $formData = $link->meta['form_data'] ?? [];

        $itemsRecurrentes = $venta?->items?->filter(fn ($i) =>
            $i->servicio && $i->servicio->tipo->value === 'recurrente'
        ) ?? collect();

        // ✅ Pago inicial REAL (con IVA) => solo si > 0
        $tienePagoInicialReal = $this->tienePagoInicialReal($venta, $formData);

        if ($tienePagoInicialReal) {
            return redirect()->route('conversion.pago-inicial', ['token' => $link->token]);
        }

        if ($itemsRecurrentes->isNotEmpty()) {
            return redirect()->route('conversion.pago-recurrente', ['token' => $link->token]);
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

public function paymentOptions(Request $request, string $token): RedirectResponse
{
    /** @var LeadConversionLink $link */
    $link = $request->attributes->get('conversion_link');
    if (!$link) abort(404);

    $venta = null;
    if (!empty($link->meta['existing_venta_id'])) {
        $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
    }

    $data = $request->validate([
        // pago inicial: stripe o transferencia
        'pago_inicial_metodo' => 'nullable|in:stripe,transferencia',
        // recurrente: tarjeta o domiciliacion
        'recurrente_metodo'   => 'nullable|in:tarjeta,domiciliacion',
    ]);

    // Guardamos en meta del link (fuente de verdad del flujo público)
    $meta = $link->meta ?? [];
    if (!empty($data['pago_inicial_metodo'])) {
        $meta['pago_inicial_metodo'] = $data['pago_inicial_metodo'];
    }
    if (!empty($data['recurrente_metodo'])) {
        $meta['recurrente_metodo'] = $data['recurrente_metodo'];
    }
    $meta['payment_options_set_at'] = now()->toDateTimeString();
    $link->meta = $meta;
    $link->save();

    // Persistimos el método de pago inicial en la venta (para que finished lo lea)
    if ($venta && !empty($data['pago_inicial_metodo'])) {
        $venta->pago_inicial_metodo = $data['pago_inicial_metodo'];
        $venta->save();
    }

    // 1) Si hay pago inicial pendiente y eligió stripe -> ir a pagar
    if ($venta) {
        $itemsUnicos = $venta->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'unico');
        $importeSinIvaInicial = $itemsUnicos->sum(fn ($i) => (float) $i->subtotal_aplicado);

        // IVA para estimar si hay pago inicial (misma lógica que finished)
        $cliente = $venta->cliente;
        $form = $link->meta['form_data'] ?? [];
        $cpCliente   = $form['cp'] ?? ($cliente->codigo_postal ?? '');
        $provCliente = $form['provincia'] ?? ($cliente->provincia ?? '');
        $porcentajeIva = Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
        $factorIva     = 1 + ($porcentajeIva / 100);
        $importePagoInicial = round($importeSinIvaInicial * $factorIva, 2);

        $tienePagoInicialPendiente = ($importePagoInicial > 0) && !$venta->tienePagoInicialCompletado();
        if ($tienePagoInicialPendiente && (($data['pago_inicial_metodo'] ?? null) === 'stripe')) {
            return redirect()->route('payment.pay', ['venta' => $venta->id]);
        }
    }

    // ✅ 2) Si no hay pago inicial o es transferencia -> si hay recurrente, NO vamos a setup.
    // Vamos al paso "pago-recurrente" para poder preguntar: usar tarjeta existente u otra.
    $recurrenteMetodo = $data['recurrente_metodo'] ?? ($link->meta['recurrente_metodo'] ?? null);
    if ($recurrenteMetodo) {
        return redirect()->route('conversion.pago-recurrente', ['token' => $link->token]);
    }

    // 3) Si no hay nada más, finished (mostrará lo pendiente)
    return redirect()->route('conversion.finished', ['token' => $link->token]);
}

public function pagoInicial(string $token, Request $request)
{
    /** @var LeadConversionLink $link */
    $link = $request->attributes->get('conversion_link');
    if (! $link) abort(404);

    $venta = null;
    if (! empty($link->meta['existing_venta_id'])) {
        $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
    }

    if (! $venta) {
        return redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    $formData = $link->meta['form_data'] ?? [];

    // ✅ Si el importe real del pago inicial es 0 -> saltamos este paso
    if (! $this->tienePagoInicialReal($venta, $formData)) {
        $tieneRecurrente = $venta->items->contains(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

        return $tieneRecurrente
            ? redirect()->route('conversion.pago-recurrente', ['token' => $link->token])
            : redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    return view('public.conversion.pago-inicial', [
        'link'  => $link,
        'lead'  => $link->lead,
        'venta' => $venta,
    ]);
}


public function guardarPagoInicial(Request $request, string $token): RedirectResponse
{
    /** @var LeadConversionLink $link */
    $link = $request->attributes->get('conversion_link');
    if (! $link) abort(404);

    $venta = null;
    if (! empty($link->meta['existing_venta_id'])) {
        $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
    }

    if (! $venta) {
        return redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    $formData = $link->meta['form_data'] ?? [];

    // ✅ Si no hay pago inicial real, no pedimos método y saltamos
    if (! $this->tienePagoInicialReal($venta, $formData)) {
        $tieneRecurrente = $venta->items->contains(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

        return $tieneRecurrente
            ? redirect()->route('conversion.pago-recurrente', ['token' => $link->token])
            : redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    $data = $request->validate([
        'pago_inicial_metodo' => 'required|in:stripe,transferencia',
    ]);

    // Guardar en link meta + venta (para que finished lo lea)
    $meta = $link->meta ?? [];
    $meta['pago_inicial_metodo'] = $data['pago_inicial_metodo'];
    $link->meta = $meta;
    $link->save();

    $venta->pago_inicial_metodo = $data['pago_inicial_metodo'];
    $venta->save();

        // ✅ Enviar email de contrato ahora (ya sabemos si es transferencia o stripe)
        try {
            $meta = $link->meta ?? [];
            $pdfRelPath = $meta['pdf'] ?? null;

            // Idempotencia: si ya se envió, no repetir
            $yaEnviado = LeadAutoEmailLog::where('lead_id', $link->lead->id)
                ->where('template_identifier', 'contract_signed')
                ->where('status', 'sent')
                ->exists();

            if (! $yaEnviado && $pdfRelPath) {
                $absolutePdfPath = storage_path('app/public/' . $pdfRelPath);

                Mail::to($venta->cliente->email_contacto)->send(
                    new \App\Mail\ContractSignedMail(
                        $link->lead,
                        $absolutePdfPath,
                        $venta->fresh(['items.servicio', 'cliente'])
                    )
                );

                LeadAutoEmailLog::create([
                    'lead_id'              => $link->lead->id,
                    'estado'               => 'firmado',
                    'intento'              => 1,
                    'template_identifier'  => 'contract_signed',
                    'subject'              => 'Aquí tienes tu contrato firmado con AsesorFy',
                    'body_preview'         => 'Contrato firmado adjunto enviado al cliente.',
                    'scheduled_at'         => now(),
                    'sent_at'              => now(),
                    'status'               => 'sent',
                    'mail_driver'          => config('mail.default'),
                    'triggered_by_user_id' => 9999,
                    'trigger_source'       => 'guardar_pago_inicial',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("❌ Error enviando ContractSignedMail en guardarPagoInicial: " . $e->getMessage());
        }

    // Si elige tarjeta → ir a checkout del pago inicial
    if ($data['pago_inicial_metodo'] === 'stripe') {
        return redirect()->route('payment.pay', ['venta' => $venta->id]);
    }

    // Si elige transferencia → siguiente paso (recurrente si existe)
    $tieneRecurrente = $venta->items->contains(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

    return $tieneRecurrente
        ? redirect()->route('conversion.pago-recurrente', ['token' => $link->token])
        : redirect()->route('conversion.finished', ['token' => $link->token]);
}


public function pagoRecurrente(string $token, Request $request)
{
    /** @var LeadConversionLink $link */
    $link = $request->attributes->get('conversion_link');
    if (! $link) abort(404);

    $venta = null;
    if (! empty($link->meta['existing_venta_id'])) {
        $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
    }

    if (! $venta) {
        return redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    $venta->loadMissing('items.servicio', 'cliente');

    $tieneRecurrente = $venta->items?->contains(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente') ?? false;
    if (! $tieneRecurrente) {
        return redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    $pagoInicialMetodo = $venta->pago_inicial_metodo
        ?? ($link->meta['pago_inicial_metodo'] ?? 'stripe');

    // ✅ SOLO consideramos "tarjeta guardada" si el default_payment_method es CARD
    $hasDefaultPM   = false; // = "hay tarjeta guardada"
    $defaultPmLabel = null;  // ej: "Visa •••• 4242"
    $defaultPmType  = null;  // 'card' | null

    $cliente = $venta->cliente;

    if ($cliente && $cliente->stripe_customer_id) {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            if (app()->isLocal()) Stripe::setVerifySslCerts(false);

            $customer = Customer::retrieve([
                'id'     => $cliente->stripe_customer_id,
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPm = $customer->invoice_settings->default_payment_method ?? null;

            // ✅ Solo cuenta si es tarjeta (->card)
            if ($defaultPm && isset($defaultPm->card)) {
                $hasDefaultPM = true;
                $defaultPmType = 'card';

                $brand = ucfirst($defaultPm->card->brand ?? 'Card');
                $last4 = $defaultPm->card->last4 ?? '0000';
                $defaultPmLabel = "{$brand} •••• {$last4}";
            }
        } catch (\Throwable $e) {
            Log::warning("pagoRecurrente: no se pudo leer default_payment_method: " . $e->getMessage());
        }
    }
    $pagoInicialCompletado = $venta->tienePagoInicialCompletado();


    return view('public.conversion.pago-recurrente', [
        'link'              => $link,
        'lead'              => $link->lead,
        'venta'             => $venta,
        'pagoInicialMetodo' => $pagoInicialMetodo,
        'pagoInicialCompletado'  => $pagoInicialCompletado,
        'hasDefaultPM'      => $hasDefaultPM,
        'defaultPmLabel'    => $defaultPmLabel,
        'defaultPmType'     => $defaultPmType,
    ]);
}




public function guardarPagoRecurrente(Request $request, string $token): RedirectResponse
{
    /** @var LeadConversionLink $link */
    $link = $request->attributes->get('conversion_link');
    if (! $link) abort(404);

    $venta = null;
    if (! empty($link->meta['existing_venta_id'])) {
        $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
    }

    if (! $venta || ! $venta->cliente) {
        return redirect()->route('conversion.finished', ['token' => $link->token]);
    }

    $data = $request->validate([
        'recurrente_metodo' => 'required|in:tarjeta,domiciliacion',
        // ✅ obligatorio si eliges tarjeta
        'tarjeta_accion'    => 'required_if:recurrente_metodo,tarjeta|in:usar_existente,usar_otra',
    ]);

    $metodo = $data['recurrente_metodo'];

    // Guardar elección en el link (meta)
    $meta = $link->meta ?? [];
    $meta['recurrente_metodo'] = $metodo;

    if ($metodo === 'tarjeta') {
        $meta['tarjeta_accion'] = $data['tarjeta_accion'];
    } else {
        unset($meta['tarjeta_accion']);
    }

    $link->meta = $meta;
    $link->save();

    // Guardar preferencia local
    $venta->cliente->preferencia_pago_recurrente = $metodo;
    $venta->cliente->saveQuietly();

    // A) Domiciliación -> setup SEPA
    if ($metodo === 'domiciliacion') {
        return redirect()->route('stripe.setup-sepa', ['token' => $link->token]);
    }

    // B) Tarjeta -> decidir si usar existente o meter otra
    $cliente = $venta->cliente;

    // Si el usuario pidió explícitamente "usar otra", vamos directo a setup-card
    if ($data['tarjeta_accion'] === 'usar_otra') {
        return redirect()->route('stripe.setup-card', ['token' => $link->token]);
    }

    // Comprobamos si existe una TARJETA por defecto (no SEPA) en Stripe
    $tieneTarjetaDefault = false;

    if ($cliente->stripe_customer_id) {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            if (app()->isLocal()) Stripe::setVerifySslCerts(false);

            $customer = Customer::retrieve([
                'id'     => $cliente->stripe_customer_id,
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPM = $customer->invoice_settings->default_payment_method ?? null;

            // ✅ Solo cuenta si es TARJETA
            $tieneTarjetaDefault = (bool) ($defaultPM && isset($defaultPM->card));
        } catch (\Throwable $e) {
            Log::warning('No se pudo comprobar default_payment_method: ' . $e->getMessage());
        }
    }

    // Si NO hay tarjeta default -> setup-card
    if (! $tieneTarjetaDefault) {
        return redirect()->route('stripe.setup-card', ['token' => $link->token]);
    }

    // Por defecto: usar tarjeta existente -> ir a finished y disparar el auto-proceso
    return redirect()
        ->route('conversion.finished', ['token' => $link->token])
        ->with('payment_setup_success', true);
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
        $venta = Venta::with('items.servicio', 'cliente', 'proyectos', 'facturas')->find($link->meta['existing_venta_id']);
    }

    $cliente = $venta?->cliente;

    // -------------------------------------------------
    // Método pago inicial (fuente de verdad)
    // -------------------------------------------------
    $blueprint = $link->meta['sale_blueprint'] ?? [];
    $metodoPagoInicial = $venta?->pago_inicial_metodo
        ?? ($link->meta['pago_inicial_metodo'] ?? ($blueprint['pago_inicial_metodo'] ?? 'stripe'));

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
    $porcentajeIva = Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
    $factorIva     = 1 + ($porcentajeIva / 100);

    $importeSinIvaInicial = $itemsUnicos->sum(fn ($i) => (float) $i->subtotal_aplicado);
    $importePagoInicial   = round($importeSinIvaInicial * $factorIva, 2);

    $precioMensualSinIva = $itemsRecurrentes->sum(fn ($i) => (float) $i->subtotal_aplicado);
    $totalRecurrenteConIva = round($precioMensualSinIva * $factorIva, 2);

    // 3. ESTADO PAGOS
    $tieneRecurrente = $itemsRecurrentes->isNotEmpty();

    $tienePagoInicialPendiente = ($importePagoInicial > 0) && ($venta ? !$venta->tienePagoInicialCompletado() : true);

    $preferencia = $link->meta['recurrente_metodo']
        ?? ($cliente?->preferencia_pago_recurrente ?? 'tarjeta');

    $pagoRecurrenteCompletado = false;
    $cardInfo = null;

    // 4. VERIFICACIÓN STRIPE (sin redirects)
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
                    $cardInfo = [
                        'type'  => 'card',
                        'brand' => ucfirst($defaultPM->card->brand),
                        'last4' => $defaultPM->card->last4,
                    ];
                } elseif (isset($defaultPM->sepa_debit)) {
                    $cardInfo = [
                        'type'  => 'sepa',
                        'last4' => $defaultPM->sepa_debit->last4,
                    ];
                }
            } else {
                $pagoRecurrenteCompletado = false;
            }
        } catch (Throwable $e) {
            Log::error('Stripe finished error: ' . $e->getMessage());
        }
    } else {
        $pagoRecurrenteCompletado = false;
    }

    // =====================================================================
    // 🔥 AUTO-PROCESO AL VOLVER DE SETUP
    // =====================================================================
    if (session('payment_setup_success') && $venta && $cliente?->stripe_customer_id) {

        // ✅ BLINDAJE: si el pago inicial es transferencia y está pendiente,
        // NUNCA marques la venta como pagada por Stripe
        if ($metodoPagoInicial === 'transferencia' && $tienePagoInicialPendiente) {
            Log::info("⏭️ payment_setup_success: Pago inicial es TRANSFERENCIA. No auto-cobro ni completo venta.", [
                'venta_id' => $venta->id,
            ]);

            $request->session()->forget('payment_setup_success');
        } else {

            Log::info("💳 Método de pago guardado. Procesando pendientes para Venta #{$venta->id}");

            try {
                Stripe::setApiKey(config('services.stripe.secret'));
                if (app()->isLocal()) Stripe::setVerifySslCerts(false);

                // A) Auto-cobro pago inicial SOLO si:
                // - hay pago inicial pendiente
                // - y el método inicial es Stripe
                // - y el default PM es TARJETA (no SEPA)
                if ($tienePagoInicialPendiente && $metodoPagoInicial === 'stripe') {

                    if (($cardInfo['type'] ?? null) !== 'card') {
                        throw new Exception("Auto-cobro inicial requiere TARJETA. Default PM no es card.");
                    }

                    Log::info("💰 Auto-cobro pago inicial ({$importePagoInicial}€) ...");

                    foreach ($itemsUnicos as $item) {
                        $importeItemBruto = round($item->subtotal_aplicado * $factorIva, 2);
                        if ($importeItemBruto <= 0) continue;

                        InvoiceItem::create([
                            'customer'    => $cliente->stripe_customer_id,
                            'amount'      => (int) ($importeItemBruto * 100),
                            'currency'    => 'eur',
                            'description' => $item->nombre_personalizado ?? $item->servicio->nombre,
                            'metadata'    => ['venta_id' => $venta->id, 'tipo' => 'pago_unico'],
                        ]);
                    }

                    $invoice = Invoice::create([
                        'customer'     => $cliente->stripe_customer_id,
                        'auto_advance' => true,
                    ]);

                    $invoice->finalizeInvoice();
                    $invoice->pay();

                    $invoice = Invoice::retrieve($invoice->id);

                    if ($invoice->status !== 'paid') {
                        throw new Exception("Cobro no completado. Estado: " . $invoice->status);
                    }

                    $paymentIntentId = $invoice->payment_intent ?? null;

                    // ✅ Esto marca la venta como completada y genera factura inicial
                    $venta->procesarCobroInicial(now(), 'stripe_automatico', $paymentIntentId);

                    $tienePagoInicialPendiente = false;
                    Log::info("✅ Pago inicial OK. Invoice {$invoice->id}");
                }

                // B) Activar SOLO VENTAS "SOLO RECURRENTE" (sin cobro inicial)
                // Si hay un pago inicial (aunque ya esté pagado), NO forzamos aquí.
                if ($importePagoInicial <= 0 && $tieneRecurrente && $pagoRecurrenteCompletado) {
                    if ($venta->estado !== VentaEstadoEnum::COMPLETADA) {
                        $venta->procesarCobroInicial(now(), 'suscripcion_directa', null, $form);
                    }
                }

            } catch (Throwable $e) {
                Log::error("❌ Error en auto-proceso tras setup: " . $e->getMessage());
            } finally {
                $request->session()->forget('payment_setup_success');
            }
        }

        // refrescar todo tras cambios
        $venta = $venta->fresh(['items.servicio', 'cliente', 'proyectos', 'facturas']);
        $cliente = $venta?->cliente;

        $itemsUnicos = $venta?->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'unico') ?? collect();
        $itemsRecurrentes = $venta?->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente') ?? collect();

        $tieneRecurrente = $itemsRecurrentes->isNotEmpty();
        $tienePagoInicialPendiente = ($importePagoInicial > 0) && ($venta ? !$venta->tienePagoInicialCompletado() : true);
    }

    // ✅ Enviar bienvenida cuando:
            // - contrato firmado
            // - recurrente configurado (o no hay recurrente)
            // - pago inicial: si es stripe, debe estar pagado; si es transferencia, vale “pendiente”
            if ($venta && $cliente) {
                $contratoFirmado = (bool) $venta->signed_at;

                $recurrenteListo = (! $tieneRecurrente) || $pagoRecurrenteCompletado;

                $pagoInicialOk = true;
                if ($importePagoInicial > 0) {
                    if ($metodoPagoInicial === 'stripe') {
                        $pagoInicialOk = ! $tienePagoInicialPendiente; // debe estar pagado
                    } elseif ($metodoPagoInicial === 'transferencia') {
                        $pagoInicialOk = true; // permitido “esperando transferencia”
                    }
                }

                if ($contratoFirmado && $recurrenteListo && $pagoInicialOk) {
                   $venta->enviarBienvenidaSiProcede('conversion_finished', [
                        'tiene_unico'            => ($importePagoInicial > 0),
                        'pago_inicial_metodo'    => $metodoPagoInicial,
                        'pago_inicial_pendiente' => $tienePagoInicialPendiente,
                        'recurrente_listo'       => $recurrenteListo,
                    ]);

                }
            }


    // =========================
    // PREPARACIÓN FINAL DE VISTA
    // =========================
    $prorrateo = null;
    if ($itemsRecurrentes->isNotEmpty() && !$esperaProyecto) {
        $hoy = now();
        $diasMes = $hoy->daysInMonth;
        $diasRestantes = ($diasMes - $hoy->day) + 1;

        $totalConIva = round($itemsRecurrentes->sum(fn ($i) => (float) $i->subtotal_aplicado) * $factorIva, 2);
        $totalSinIva = $itemsRecurrentes->sum(fn ($i) => (float) $i->subtotal_aplicado);

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

    $ibanEmpresa = null;
    foreach (['empresa_iban', 'iban_empresa', 'empresa_cuenta_bancaria', 'empresa_iban_transferencias'] as $k) {
        $ibanEmpresa = DB::table('variables_configuracion')->where('nombre_variable', $k)->value('valor_variable');
        if ($ibanEmpresa) break;
    }

    $conceptoTransferencia = $venta
        ? ("VENTA {$venta->id}" . ($cliente?->razon_social ? " - {$cliente->razon_social}" : ''))
        : ("CONVERSION - {$link->token}");

    return view('public.conversion.finished', [
        'lead'                      => $lead,
        'link'                      => $link,
        'form'                      => $form,
        'venta'                     => $venta,

        'importePagoInicial'        => $importePagoInicial,
        'pagoRecurrenteCompletado'  => $pagoRecurrenteCompletado,
        'tieneRecurrente'           => $tieneRecurrente,
        'tienePagoInicialPendiente' => $tienePagoInicialPendiente,

        'totalRecurrenteMensual'    => $totalRecurrenteConIva,
        'importeSinIvaRecurrente'   => $precioMensualSinIva,
        'nombreServicioRecurrente'  => $nombreServicioRecurrente,
        'prorrateo'                 => $prorrateo,
        'porcentajeIva'             => $porcentajeIva,

        'cardInfo'                  => $cardInfo,
        'pdfUrl'                    => $pdfUrl,
        'esperaProyecto'            => $esperaProyecto,

        'preferencia'               => $preferencia,
        'metodoPagoInicial'         => $metodoPagoInicial,
        'ibanEmpresa'               => $ibanEmpresa,
        'conceptoTransferencia'     => $conceptoTransferencia,
    ]);
}




    private function redirectStripeSetup($preferencia, $token)
    {
        $route = $preferencia === 'domiciliacion' ? 'stripe.setup-sepa' : 'stripe.setup-card';
        return redirect()->route($route, ['token' => $token]);
    }


/**
 * ✅ Calcula el IMPORTE REAL del pago inicial (solo servicios UNICOS) + IVA.
 * Si el total base es 0 (o negativo), devuelve 0.
 */
private function calcularImportePagoInicialConIva(?Venta $venta, array $form = []): float
{
    if (! $venta) {
        return 0.0;
    }

    $venta->loadMissing('items.servicio', 'cliente');

    $itemsUnicos = $venta->items->filter(fn ($i) =>
        $i->servicio && ($i->servicio->tipo->value ?? $i->servicio->tipo) === 'unico'
    );

    // Base imponible (solo lo aplicado). Si queda 0 por descuento, aquí será 0.
    $base = (float) $itemsUnicos->sum(fn ($i) => (float) ($i->subtotal_aplicado ?? 0));

    if ($base <= 0) {
        return 0.0;
    }

    $cliente = $venta->cliente;
    $cpCliente   = $form['cp'] ?? ($cliente->codigo_postal ?? '');
    $provCliente = $form['provincia'] ?? ($cliente->provincia ?? '');

    $porcentajeIva = Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
    $factorIva     = 1 + ($porcentajeIva / 100);

    return round($base * $factorIva, 2);
}

/**
 * ✅ “Hay pago inicial” SOLO si el importe real (con IVA) es > 0
 */
private function tienePagoInicialReal(?Venta $venta, array $form = []): bool
{
    return $this->calcularImportePagoInicialConIva($venta, $form) > 0;
}






}