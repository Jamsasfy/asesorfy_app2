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
use App\Models\ClienteSuscripcion;
use App\Services\ConfiguracionService;


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

    $fmtMoney = fn (float $n) => number_format($n, 2, ',', '.');

    $toBool = static function ($v): bool {
        if (is_bool($v)) return $v;
        if ($v === null || $v === '') return false;
        $parsed = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? (bool) $v;
    };

    // Fechas base (solo para NO diferidos)
    $hoy = \Carbon\Carbon::now()->locale('es');
    $mesActualAnio = ucfirst($hoy->translatedFormat('F Y'));
    $diasMes = $hoy->daysInMonth ?: 30;
    $diasRestantes = ($diasMes - $hoy->day) + 1;

    $primerCobroFecha = $hoy->copy()->addMonthNoOverflow()->startOfMonth();
    $textoPrimerCobroStd = '1 de ' . ucfirst($primerCobroFecha->translatedFormat('F Y'));

    /**
     * ✅ BLOQUEO GLOBAL:
     * si algún ÚNICO bloquea_recurrente => TODOS los recurrentes quedan diferidos.
     *
     * Soporte:
     * - editable => usa $s['bloquea_recurrente']
     * - no editable => usa $s['servicio_bloquea_recurrente']
     * - legacy fallback (si aún no existe el campo en blueprint): usa requiere_proyecto como última opción
     */
    $bloqueoRecurrenteGlobal = collect($services)->contains(function ($s) use ($toBool) {
        if (($s['tipo'] ?? '') !== 'unico') return false;

        $esEditable = $toBool($s['es_editable'] ?? false);

        $flag = $esEditable
            ? $toBool($s['bloquea_recurrente'] ?? false)
            : $toBool($s['servicio_bloquea_recurrente'] ?? ($s['bloquea_recurrente'] ?? false));

        // Legacy: si no viene nada y antes lo usabas mal, mantenemos compatibilidad
        if (!isset($s['bloquea_recurrente']) && !isset($s['servicio_bloquea_recurrente'])) {
            $flag = $esEditable
                ? $toBool($s['requiere_proyecto'] ?? false)
                : $toBool($s['servicio_requiere_proyecto'] ?? false);
        }

        return $flag;
    });

    // Helper cálculo línea
    $calcLine = function (array $s) use ($fmtMoney, $diasMes, $diasRestantes): array {
        $units = (float) ($s['unidades'] ?? 1);
        if ($units <= 0) $units = 1;

        $baseTotal = (float) ($s['subtotal_base'] ?? 0)
            ?: ((float) ($s['precio_base_original'] ?? 0) * $units)
            ?: ((float) ($s['precio_base'] ?? 0) * $units);

        if ($baseTotal <= 0 && !empty($s['servicio_id'])) {
            $baseTotal = (float) (\App\Models\Servicio::find($s['servicio_id'])?->precio_base ?? 0) * $units;
        }

        $promo = $s['descuento'] ?? null;
        $aplicaPromo = (bool) data_get($promo, 'aplicar', false);
        $promoTipo  = (string) data_get($promo, 'tipo', '');
        $promoValor = (float) (data_get($promo, 'valor') ?: 0);

        $finalTotal = $aplicaPromo ? (float) ($s['subtotal_final'] ?? 0) : $baseTotal;

        if ($aplicaPromo && (!isset($s['subtotal_final']) || $s['subtotal_final'] === null || $s['subtotal_final'] === '')) {
            if ($promoTipo === 'porcentaje' && $promoValor > 0) {
                $finalTotal = max(0, $baseTotal * (1 - ($promoValor / 100)));
            } elseif (in_array($promoTipo, ['fijo', 'importe', 'euros'], true) && $promoValor > 0) {
                $finalTotal = max(0, $baseTotal - $promoValor);
            } else {
                $finalTotal = $baseTotal;
            }
        }

        $discount = max(0, $baseTotal - $finalTotal);
        $desc = $s['descuento_descripcion'] ?? null;

        $dtoMeses = 0;
        if ($aplicaPromo) {
            $dtoMeses = (int) ($promo['meses'] ?? $s['descuento_duracion_meses'] ?? 0);
        }

        $importeProrrata = ($finalTotal / $diasMes) * $diasRestantes;

        return [
            'base'        => $baseTotal,
            'final'       => max(0, (float) $finalTotal),
            'discount'    => $discount,
            'desc'        => $desc,
            'promo_meses' => max(0, (int) $dtoMeses),
            'promo_tipo'  => $promoTipo,
            'promo_valor' => $promoValor,
            'aplicaPromo' => $aplicaPromo,
            'importe_prorrata' => $importeProrrata,
            'base_fmt'    => $fmtMoney($baseTotal),
            'final_fmt'   => $fmtMoney((float) $finalTotal),
        ];
    };

    // ✅ CLAVE: forzar herencia de fuente en tabla+filas auxiliares (section headers/footer)
    $inheritFont = "font-family: inherit !important;";

    $stSectionHeader = "{$inheritFont} font-weight:bold; color:#64748b; padding:6px 3px; text-transform:uppercase; letter-spacing:.05em;";
    $stRight = "text-align:right; white-space:nowrap;";

    $stTable = "{$inheritFont} width:100%; border-collapse:collapse; margin-top:12px; margin-bottom:12px;";
    $stTh = "{$inheritFont} text-align:left; padding:8px 6px; border-bottom:1px solid #e2e8f0; font-weight:bold; color:#334155;";
    $stTd = "{$inheritFont} padding:10px 6px; border-bottom:1px dashed #e2e8f0; vertical-align:top;";

    if (!empty($services)) {
        $listR = [];
        $listU = [];

        foreach ($services as $svc) {
            if (($svc['tipo'] ?? '') === 'recurrente') $listR[] = $svc;
            else $listU[] = $svc;
        }

        $htmlTablaCompleta .= "<table class='service-table' style='{$stTable}'>";

        $htmlTablaCompleta .= "<thead><tr>
            <th style='{$stTh}'>Servicio Contratado</th>
            <th style='{$stTh} text-align:right;'>Importe</th>
        </tr></thead><tbody>";

        // ==========================
        // RECURRENTES
        // ==========================
        if (!empty($listR)) {
            $htmlTablaCompleta .= "<tr><td colspan='2' style='{$stSectionHeader}'>Servicios Recurrentes (Mensuales)</td></tr>";

            foreach ($listR as $s) {
                $line = $calcLine($s);

                $nombre = $s['nombre'] ?? 'Servicio';
                if (($s['unidades'] ?? 1) > 1) {
                    $u = (int) $s['unidades'];
                    $nombre .= " <strong>(x{$u})</strong>";
                }

                $cobro = $s['cobro_primer_mes'] ?? 'prorrata';
                if (!empty($s['no_cobrar_primer_periodo']) && $toBool($s['no_cobrar_primer_periodo'])) {
                    $cobro = 'gratis';
                }

                // ✅ DIFERIDO SOLO POR BLOQUEO GLOBAL (un único bloqueante en venta)
                $rowBloqueado = $bloqueoRecurrenteGlobal;

                $importeHtml = "";

                if ($line['base'] <= 0) {
                    $importeHtml = "—";
                } else {
                    // 1) Base
                    $importeHtml .= "<div style='font-weight:700; color:#0f172a; line-height:1.2;'>{$line['base_fmt']} €/mes</div>";

                    if ($rowBloqueado) {
                        // ✅ DIFERIDO: no pintamos fechas concretas
                        $importeHtml .= "<div style='margin-top:3px; font-weight:800; color:#9a3412; line-height:1.2;'>
                            Inicio diferido: se facturará cuando se active el servicio.
                        </div>";

                        // Condición primer mes
                        if ($cobro === 'gratis') {
                            $importeHtml .= "<div style='margin-top:3px; font-weight:800; color:#065f46; line-height:1.2;'>
                                Tras activación: mes 1 gratis (0,00 €).
                            </div>";
                        } elseif ($cobro === 'completo') {
                            $precioMes1 = $line['aplicaPromo'] ? $line['final_fmt'] : $line['base_fmt'];
                            $extraPromo = $line['aplicaPromo'] ? " (con promoción)" : "";
                            $importeHtml .= "<div style='margin-top:3px; font-weight:800; color:#0284c7; line-height:1.2;'>
                                Tras activación: mes 1 completo{$extraPromo}: {$precioMes1} €.
                            </div>";
                        } else {
                            $importeHtml .= "<div style='margin-top:3px; font-weight:800; color:#ea580c; line-height:1.2;'>
                                Tras activación: mes 1 prorrateado (según día de activación).
                            </div>";
                        }

                        // Promo por meses “desde activación”
                        $dtoMeses = (int) ($line['promo_meses'] ?? 0);
                        if ($line['aplicaPromo'] && $dtoMeses > 0) {
                            $pTipo  = $line['promo_tipo'];
                            $pVal   = (float) $line['promo_valor'];
                            $txtPromo = ($pTipo === 'porcentaje' && $pVal > 0) ? "Promoción {$pVal}%" : "Promoción";

                            if ($cobro === 'gratis') {
                                $mIni = 2; $mFin = 1 + $dtoMeses; $mNormal = $mFin + 1;
                            } else {
                                $mIni = 1; $mFin = $dtoMeses; $mNormal = $mFin + 1;
                            }

                            $importeHtml .= "<div style='margin-top:4px; font-weight:800; color:#0f172a; line-height:1.2;'>
                                Meses {$mIni}" . ($mFin > $mIni ? "-{$mFin}" : "") . ": {$txtPromo}: {$line['final_fmt']} €/mes.
                            </div>";

                            $importeHtml .= "<div style='margin-top:4px; font-size:10px; color:#94a3b8; font-weight:600;'>
                                Desde mes {$mNormal}: cuota normal {$line['base_fmt']} €/mes.
                            </div>";
                        } elseif ($line['aplicaPromo'] && $dtoMeses === 0) {
                            $importeHtml .= "<div style='margin-top:4px; font-weight:800; color:#0f172a; line-height:1.2;'>
                                Precio con descuento desde activación: {$line['final_fmt']} €/mes.
                            </div>";
                        }

                    } else {
                        // ✅ NO DIFERIDO: lógica con fechas
                        if ($cobro === 'gratis') {
                            $importeHtml .= "<div style='margin-top:3px; font-weight:700; color:#065f46; line-height:1.2;'>
                                Primer mes ({$mesActualAnio}): 0,00 €
                            </div>";
                            $importeHtml .= "<div style='margin-top:2px; font-size:10px; color:#64748b;'>
                                Se empieza a facturar el {$textoPrimerCobroStd}.
                            </div>";
                        } elseif ($cobro === 'completo') {
                            $precioMesActual = $line['aplicaPromo'] ? $line['final_fmt'] : $line['base_fmt'];
                            $extraPromo = $line['aplicaPromo'] ? " (con promoción)" : "";
                            $importeHtml .= "<div style='margin-top:3px; font-weight:700; color:#0284c7; line-height:1.2;'>
                                Mes completo de {$mesActualAnio}{$extraPromo}: {$precioMesActual} €
                            </div>";
                        } else {
                            $extraPromo = $line['aplicaPromo'] ? " (con promoción)" : "";
                            $precioProrrata = $fmtMoney($line['importe_prorrata']);
                            $importeHtml .= "<div style='margin-top:3px; font-weight:700; color:#ea580c; line-height:1.2;'>
                                Parte proporcional de {$mesActualAnio}{$extraPromo}: {$precioProrrata} €
                            </div>";
                        }

                        // Promo futura con fechas (si aplica)
                        $dtoMeses = (int) ($line['promo_meses'] ?? 0);
                        $mesesRestantesPromo = $dtoMeses;

                        if ($line['aplicaPromo'] && ($cobro === 'prorrata' || $cobro === 'completo')) {
                            $mesesRestantesPromo = max(0, $dtoMeses - 1);
                        }

                        if ($line['aplicaPromo'] && $mesesRestantesPromo > 0) {
                            $inicioPromo = $primerCobroFecha->copy()->startOfMonth();
                            $finPromo = $inicioPromo->copy()->addMonthsNoOverflow($mesesRestantesPromo - 1)->startOfMonth();

                            $iniTxt = ucfirst($inicioPromo->translatedFormat('F Y'));
                            $finTxt = ucfirst($finPromo->translatedFormat('F Y'));
                            $promoRango = ($mesesRestantesPromo === 1) ? $iniTxt : "de {$iniTxt} a {$finTxt}";

                            $promoTipo  = $line['promo_tipo'];
                            $promoValor = $line['promo_valor'];
                            $txtPromo = ($promoTipo === 'porcentaje') ? "Promoción " . (float)$promoValor . "%" : "Promoción";

                            $importeHtml .= "<div style='margin-top:4px; font-weight:700; color:#0f172a; line-height:1.2;'>
                                {$txtPromo} ({$promoRango}): {$line['final_fmt']} €/mes
                            </div>";

                            $fechaNormalidad = $primerCobroFecha->copy();
                            $mesesASumar = ($cobro === 'gratis') ? $dtoMeses : max(0, $dtoMeses - 1);
                            $fechaNormalidad->addMonthsNoOverflow($mesesASumar);

                            $txtFechaNormal = '1 de ' . ucfirst($fechaNormalidad->translatedFormat('F \d\e Y'));
                            $importeHtml .= "<div style='margin-top:4px; font-size:10px; color:#94a3b8; font-weight:600;'>
                                A partir del {$txtFechaNormal} pago cuota normal de {$line['base_fmt']} €/mes.
                            </div>";
                        } elseif ($line['aplicaPromo'] && $dtoMeses == 0) {
                            $importeHtml .= "<div style='margin-top:4px; font-weight:700; color:#0f172a;'>
                                Precio con descuento: {$line['final_fmt']} €/mes
                            </div>";
                        }
                    }
                }

                $htmlTablaCompleta .= "<tr>
                    <td style='{$stTd}'><strong>{$nombre}</strong></td>
                    <td style='{$stTd} {$stRight}'>{$importeHtml}</td>
                </tr>";
            }
        }

        // ==========================
        // ÚNICOS
        // ==========================
        if (!empty($listU)) {
            $htmlTablaCompleta .= "<tr><td colspan='2' style='{$stSectionHeader}'>Servicios de Pago Único (Inicio)</td></tr>";

            foreach ($listU as $s) {
                $line = $calcLine($s);

                $nombre = $s['nombre'] ?? 'Servicio';
                if (($s['unidades'] ?? 1) > 1) {
                    $u = (int) $s['unidades'];
                    $nombre .= " <strong>(x{$u})</strong>";
                }

                // Detectar si el ÚNICO crea proyecto (requiere ejecución)
                $ownRequiresProyecto = $toBool($s['es_editable'] ?? false)
                    ? $toBool($s['requiere_proyecto'] ?? false)
                    : $toBool($s['servicio_requiere_proyecto'] ?? false);

                if ($line['base'] <= 0) {
                    $importeHtml = "—";
                } elseif ($line['discount'] > 0.00001) {
                    $importeHtml =
                        "<div style='font-weight:700; color:#0f172a; line-height:1.2;'>{$line['base_fmt']} €</div>" .
                        "<div style='margin-top:3px; font-weight:700; color:#0f172a; line-height:1.2;'>Pago con promoción: {$line['final_fmt']} €</div>";
                } else {
                    $importeHtml = "<div style='font-weight:700; color:#0f172a; line-height:1.2;'>{$line['base_fmt']} €</div>";
                }

                if ($ownRequiresProyecto) {
                    $importeHtml .= "<div style='margin-top:3px; font-size:10px; color:#9a3412; font-weight:700; white-space:normal;'>
                        Requiere trámites/proyecto previo.
                    </div>";
                }

                $htmlTablaCompleta .= "<tr>
                    <td style='{$stTd}'><strong>{$nombre}</strong></td>
                    <td style='{$stTd} {$stRight}'>{$importeHtml}</td>
                </tr>";
            }
        }

        $htmlTablaCompleta .= "</tbody></table>";

        // ✅ footer también hereda fuente
        $htmlTablaCompleta .= "<div style='{$inheritFont} text-align:right; font-size:10px; color:#94a3b8; margin-top:6px; margin-bottom:20px;'>
            * Impuestos no incluidos
        </div>";
    } else {
        $htmlTablaCompleta = "<p><em>No se han especificado servicios.</em></p>";
    }

    $replacements = [
        '[AFY_RAZON]'         => $empresa['razon'],
        '[AFY_CIF]'           => $empresa['cif'],
        '[AFY_DIRECCION]'     => $empresa['direccion'],
        '[CLIENTE_NOMBRE]'    => $nombreCompleto,
        '[CLIENTE_DNI]'       => $dniCif,
        '[CLIENTE_DIRECCION]' => $direccionCli,
        '[CLIENTE_EMAIL]'     => $emailCli,
        '[TABLA_SERVICIOS]'   => $htmlTablaCompleta,
    ];

    // ============================================
    // DETECTAR TIPO DE CLIENTE (3 CASOS)
    // ============================================
    $estadoSociedad = $form['estado_sociedad'] ?? null;
    $esSociedadConstituida = ($estadoSociedad === 'constituida');
    $esSociedadEnConstitucion = ($estadoSociedad === 'en_constitucion');

    // Datos del firmante/representante
    $nombreFirmante = trim(($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? ''));
    $nombreRepresentante = trim(($form['nombre_representante'] ?? '') . ' ' . ($form['apellidos_representante'] ?? ''));
    $dniRepresentante = $form['dni'] ?? '';
    $cifEmpresa = $form['cif'] ?? '';
    $razonSocialEmpresa = $form['razon_social'] ?? '';

    // Generar texto "Y de otra parte..." según caso
    if ($esSociedadConstituida && !empty($cifEmpresa)) {
        // CASO 3: SOCIEDAD CONSTITUIDA (con CIF)
        $nombreParaContrato = $nombreRepresentante ?: $nombreFirmante;
        $textoOtraParte = "Y de otra parte, <strong>{$nombreParaContrato}</strong>, con DNI <strong>{$dniRepresentante}</strong>, " .
                          "en representación de <strong>{$razonSocialEmpresa}</strong>, con CIF <strong>{$cifEmpresa}</strong>, " .
                          "domicilio social en {$direccionCli}, y correo electrónico {$emailCli} " .
                          "(en adelante, \"EL CLIENTE\").";

    } elseif ($esSociedadEnConstitucion) {
        // CASO 2: SOCIEDAD EN CONSTITUCIÓN (sin CIF)
        $textoOtraParte = "Y de otra parte, <strong>{$nombreFirmante}</strong>, con DNI <strong>{$dniRepresentante}</strong>, " .
                          "domicilio en {$direccionCli}, y correo electrónico {$emailCli}, " .
                          "actuando en nombre propio para la constitución de sociedad en constitución " .
                          "(en adelante, \"EL CLIENTE\")." .
                          "<p><strong>CLÁUSULA ESPECIAL:</strong> El presente contrato se suscribe para la prestación " .
                          "de servicios vinculados a la constitución de una sociedad mercantil. " .
                          "Una vez inscrita la sociedad en el Registro Mercantil, los servicios " .
                          "recurrentes contratados se entenderán automáticamente transferidos a " .
                          "la persona jurídica resultante.</p>";

    } else {
        // CASO 1: AUTÓNOMO (persona física) - VALOR POR DEFECTO
        $textoOtraParte = "Y de otra parte, <strong>{$nombreFirmante}</strong>, con DNI/NIF <strong>{$dniCif}</strong>, " .
                          "domicilio en {$direccionCli}, y correo electrónico {$emailCli} " .
                          "(en adelante, \"EL CLIENTE\").";
    }

    // Añadir al array de replacements
    $replacements['[TEXTO_OTRA_PARTE]'] = $textoOtraParte;

    $dbKeys = [
        'contrato_cabecera',
        'contrato_marco_legal',
        'contrato_condiciones_grales',
        'servicio_recurrentes',
        'servicio_unicos',
        'anexo_economico',
        'anexo_rgpd_ia',
    ];

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
        /** @var \App\Models\LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        $lead      = $link->lead;
        $prefilled = $link->meta['form_data'] ?? [];
        $blueprint = $link->meta['sale_blueprint'] ?? [];

        // Venta asociada (si existe)
        $venta = null;
        if (! empty($link->meta['existing_venta_id'])) {
            $venta = \App\Models\Venta::with('items.servicio', 'cliente', 'suscripciones')
                ->find($link->meta['existing_venta_id']);
        }

        // ✅ 0) Si hay venta COMPLETADA -> solo finalizamos si NO hay recurrente pendiente
        if ($venta && ($venta->estado?->value ?? null) === \App\Enums\VentaEstadoEnum::COMPLETADA->value) {

            $venta->loadMissing('items.servicio', 'suscripciones');

            $tieneRecurrente = $venta->items->contains(fn ($i) =>
                $i->servicio && $i->servicio->tipo->value === 'recurrente'
            );

            $tieneSuscripcionStripe = $venta->suscripciones
                ? $venta->suscripciones->contains(fn ($s) => ! empty($s->stripe_subscription_id))
                : false;

            // Si hay recurrente pero NO hay suscripción, NO está realmente finalizada
            if ($tieneRecurrente && ! $tieneSuscripcionStripe) {
                return redirect()->route('conversion.pago-recurrente', ['token' => $link->token]);
            }

            return redirect()->route('conversion.finished', ['token' => $link->token])
                ->with('success', 'Esta contratación ya está finalizada.');
        }

        // ✅ 1) Si ya hay una VENTA firmada, NO mostramos el formulario.
        // Reanudamos el flujo donde lo dejó (pago inicial / pago recurrente / finished).
        if ($venta && ! empty($venta->signed_at)) {

            $itemsRecurrentes = $venta->items->filter(fn ($i) =>
                $i->servicio && $i->servicio->tipo->value === 'recurrente'
            );

            // ✅ Pago inicial REAL (solo si > 0 con IVA)
            $tienePagoInicialReal = $this->tienePagoInicialReal($venta, $prefilled);

            // ✅ Si hay pago inicial real y NO está pagado -> pantalla de pago inicial
            if ($tienePagoInicialReal && ! $venta->tienePagoInicialCompletado()) {
                return redirect()->route('conversion.pago-inicial', ['token' => $link->token]);
            }

           // ✅ Si el pago inicial ya está hecho (o no existe) y hay recurrente -> método recurrente
            if ($itemsRecurrentes->isNotEmpty()) {
                $recurrenteMetodo = data_get($link->meta, 'recurrente_metodo');
                $tieneSuscripcionStripe = $venta->suscripciones
                    ->contains(fn ($s) => !empty($s->stripe_subscription_id));

                // Si ya tiene método configurado Y suscripción activa en Stripe -> finished
                if ($recurrenteMetodo && $tieneSuscripcionStripe) {
                    return redirect()->route('conversion.finished', ['token' => $link->token]);
                }

                return redirect()->route('conversion.pago-recurrente', ['token' => $link->token]);
            }

            // ✅ Si no hay recurrente -> acabado
            return redirect()->route('conversion.finished', ['token' => $link->token]);
        }

        // ✅ 2) Si NO está firmado, flujo normal: mostrar formulario
        $tiposCliente   = \App\Models\TipoCliente::all();
        $legacyFormType = $link->meta['form_type'] ?? 'standard';
        $activeForms    = $this->getActiveForms($blueprint, $legacyFormType);

        return view('public.conversion.form', [
            'link' => $link,
            'lead' => $lead,
            'tipos' => $tiposCliente,
            'prefilled' => $prefilled,

            'tieneRecurrente' => $activeForms['recurrente'],
            'tieneAltaAutonomo' => $activeForms['alta_autonomo'],
            'tieneCreacionSociedad' => $activeForms['creacion_sociedad'],
            'tieneCapitalizacion' => $activeForms['capitalizacion'],
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
            'estado_sociedad' => 'nullable|string|in:constituida,en_constitucion',
            'nombre_representante' => 'nullable|string|max:255',
            'apellidos_representante' => 'nullable|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|max:50',
            'razon_social' => 'nullable|string|max:255',
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

            // Método recurrente viene del flujo público
            $recurrenteMetodo = data_get($link->meta, 'recurrente_metodo');

            // Método pago inicial
            $pagoInicialMetodo = data_get($link->meta, 'pago_inicial_metodo')
                ?? data_get($blueprint, 'pago_inicial_metodo')
                ?? null;

            // 1) Textos legales
            $textos = $this->procesarTextosLegales($blueprint, $lead, $formData);

            // 2) --- Preparar Datos del Cliente ---
            $existingClienteId = $link->meta['existing_cliente_id'] ?? null;
            $existingVentaId   = $link->meta['existing_venta_id'] ?? null;

            $cliente = $existingClienteId ? Cliente::find($existingClienteId) : null;

            // Extraemos datos básicos
            $estadoSociedad = $formData['estado_sociedad'] ?? null;
            $esSociedadConstituida = ($estadoSociedad === 'constituida');

            // Si es sociedad constituida Y tiene nombre_representante, usar esos
            // Si no, usar nombre y apellidos del paso 1
            if ($esSociedadConstituida && !empty($formData['nombre_representante'])) {
                $nombre = trim($formData['nombre_representante'] ?? '');
                $apellidos = trim($formData['apellidos_representante'] ?? '');
            } else {
                $nombre = trim($formData['nombre'] ?? '');
                $apellidos = trim($formData['apellidos'] ?? '');
            }

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

            // DNI del representante (solo para sociedades)
            $dniRepresentante = null;
            if ($estadoSociedad === 'constituida' || $estadoSociedad === 'en_constitucion') {
                $dniRepresentante = $formData['dni'] ?? null;
            }

            $dataCliente = [
                'nombre'             => $nombre,
                'apellidos'          => $apellidos,
                'dni_cif'            => $dniCif,
                'razon_social'       => $razonSocialReal,
                'dni_representante'  => $dniRepresentante,
                'nombre_comercial'   => $nombreComercial,
                'direccion'          => $formData['direccion'] ?? null,
                'codigo_postal'      => $formData['cp'] ?? null,
                'localidad'          => $formData['localidad'] ?? null,
                'provincia'          => $formData['provincia'] ?? null,
                'comunidad_autonoma' => $formData['comunidad_autonoma'] ?? null,
                'iban_impuestos'     => $formData['cuenta_bancaria_ss'] ?? null,
                'preferencia_pago_recurrente' => $recurrenteMetodo ?? ($cliente?->preferencia_pago_recurrente ?? 'tarjeta'),
                'email_contacto'     => $formData['email']    ?? $lead->email,
                'telefono_contacto'  => $formData['telefono'] ?? $lead->tfn,
                'observaciones'      => $this->construirObservaciones($formData),
            ];

            if ($cliente) {
                $cliente->update($dataCliente);
            } else {
                $dataCliente['tipo_cliente_id'] = $formData['tipo_cliente_id'] ?? 1;
                $dataCliente['comercial_id']    = $lead->asignado_id;
                $dataCliente['estado']          = 'pendiente';
                $dataCliente['fecha_alta']      = $signedAt;

                $cliente = Cliente::create($dataCliente);

                $lead->cliente_id = $cliente->id;
                $lead->save();
            }

            // Stripe customer logic
            $tieneRecurrenteEnBlueprint = collect($services)->contains(function ($s) {
                return ($s['tipo'] ?? null) === 'recurrente';
            });

            $necesitaStripeCustomer =
                $tieneRecurrenteEnBlueprint
                || ($pagoInicialMetodo === 'stripe')
                || in_array($recurrenteMetodo, ['tarjeta', 'domiciliacion'], true);

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

            // Guardamos meta link
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

            // ✅ total únicos usa subtotal_final (si existe) para no reventar descuentos
            $totalUnico = 0.0;
            foreach ($services as $s) {
                if (($s['tipo'] ?? '') === 'recurrente') {
                    continue;
                }

                $qty = (float) ($s['unidades'] ?? $s['cantidad'] ?? 1);
                if ($qty <= 0) $qty = 1;

                $subtBase  = (float) ($s['subtotal_base'] ?? 0);
                if ($subtBase <= 0) {
                    $precioBaseUnit = (float) ($s['precio_base_original'] ?? $s['precio_base'] ?? 0);
                    $subtBase = $precioBaseUnit * $qty;
                }

                $subtFinal = (float) ($s['subtotal_final'] ?? $subtBase);
                $totalUnico += $subtFinal;
            }

            if ($existingVentaId) {
                $venta = Venta::find($existingVentaId);
                if ($venta) {
                    $venta->cliente_id = $cliente->id;
                    $venta->signed_at  = $signedAt;
                    $venta->pago_inicial_metodo = $pagoInicialMetodo;
                    $venta->save();
                }
            }

            // Fallback robusto: si venía existingVentaId pero no existe, creamos nueva
            if (! $venta) {
                $venta = Venta::create([
                    'cliente_id'          => $cliente->id,
                    'lead_id'             => $lead->id,
                    'user_id'             => $lead->asignado_id,
                    'fecha_venta'         => $signedAt,
                    'importe_total'       => $totalUnico, // ✅ ya con descuentos si existen
                    'estado'              => VentaEstadoEnum::PENDIENTE,
                    'signed_at'           => $signedAt,
                    'pago_inicial_metodo' => $pagoInicialMetodo,
                ]);

                $meta = $link->meta ?? [];
                $meta['existing_venta_id'] = $venta->id;
                $link->meta = $meta;
                $link->save();
            }

            // ✅ SIEMPRE: sincronizamos items (crea o actualiza) y volcamos descuento a columnas reales
            $parseBool = static function ($raw, bool $default = false): bool {
                if ($raw === null) return $default;
                if (is_bool($raw)) return $raw;
                $v = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($v !== null) return $v;
                if (is_numeric($raw)) return ((int) $raw) === 1;
                return (bool) $raw;
            };

            foreach ($services as $s) {

                $servicioModel = null;
                $idServicio = $s['id'] ?? ($s['servicio_id'] ?? null);

                if ($idServicio) {
                    $servicioModel = Servicio::find($idServicio);
                } elseif (! empty($s['nombre'])) {
                    $servicioModel = Servicio::whereRaw('LOWER(nombre) = ?', [strtolower($s['nombre'])])->first();
                }

                $finalServiceId = $servicioModel?->id ?? $idServicio;
                if (! $finalServiceId) {
                    continue;
                }

                $qty = (float) ($s['unidades'] ?? $s['cantidad'] ?? 1);
                if ($qty <= 0) $qty = 1;

                // ✅ Precio base UNITARIO (sin descuento): prioriza precio_base_original si existe
                $precioUnitBase = (float) (
                    $s['precio_base_original']
                    ?? $s['precio_base']
                    ?? ($servicioModel?->precio_base ?? 0)
                );

                // Subtotal base (sin descuento)
                $subtotalBase = (float) ($s['subtotal_base'] ?? 0);
                if ($subtotalBase <= 0) {
                    $subtotalBase = $precioUnitBase * $qty;
                }

                // editable real (solo para decidir flags legacy)
                $servicioEsEditable = $servicioModel
                    ? (bool) ($servicioModel->es_editable ?? false)
                    : $parseBool($s['es_editable'] ?? null, false);

                // 1) CREA PROYECTO => requiere_proyecto
                // (mantengo tu lógica legacy para no romper nada)
                if ($servicioEsEditable) {
                    $requiereProyecto = $parseBool($s['requiere_proyecto'] ?? null, false);
                } else {
                    $requiereProyecto = $servicioModel
                        ? (bool) ($servicioModel->requiere_proyecto_activacion ?? false)
                        : $parseBool($s['servicio_requiere_proyecto'] ?? null, false);
                }

                // 2) BLOQUEA RECURRENTE => bloquea_recurrente
                $tipoServicio = $servicioModel?->tipo?->value ?? ($s['tipo'] ?? null);

                $vieneBloqueaExplicito = array_key_exists('bloquea_recurrente', $s) || array_key_exists('servicio_bloquea_recurrente', $s);

                if ($servicioEsEditable) {
                    $bloqueaRecurrente = $parseBool($s['bloquea_recurrente'] ?? null, false);
                } else {
                    $bloqueaRecurrente = $servicioModel
                        ? (bool) ($servicioModel->bloquea_recurrente ?? false)
                        : $parseBool($s['servicio_bloquea_recurrente'] ?? ($s['bloquea_recurrente'] ?? null), false);
                }

                if (($tipoServicio === 'unico') && ! $vieneBloqueaExplicito && $requiereProyecto) {
                    $bloqueaRecurrente = true;
                }

                if ($tipoServicio === 'recurrente') {
                    $bloqueaRecurrente = false;
                }

                // ✅ Descuento -> columnas reales (sin meta)
                $dto = $this->mapDescuentoColumns($s);

                // Si no es recurrente, no guardamos meses (por coherencia)
                $dtoMeses = $dto['meses'];
                if (($tipoServicio ?? ($s['tipo'] ?? null)) !== 'recurrente') {
                    $dtoMeses = null;
                }

                $payload = [
                    'servicio_id'          => $finalServiceId,
                    'nombre_personalizado' => $s['nombre'] ?? ($servicioModel?->nombre ?? 'Servicio'),
                    'cantidad'             => $qty,

                    // ✅ base
                    'precio_unitario'      => $precioUnitBase,
                    'subtotal'             => round($subtotalBase, 2),

                    // ✅ descuento persistido (clave)
                    'descuento_tipo'            => $dto['tipo'],
                    'descuento_valor'           => $dto['valor'],
                    'descuento_duracion_meses'  => $dtoMeses,
                    'observaciones_descuento'   => $dto['obs'],

                    // ✅ flags persistidos
                    'requiere_proyecto'    => $requiereProyecto,
                    'bloquea_recurrente'   => $bloqueaRecurrente,
                ];

                // Upsert por servicio_id (tu UI evita duplicados, esto lo hace robusto)
                $existingItem = $venta->items()->where('servicio_id', $finalServiceId)->first();

                if ($existingItem) {
                    $existingItem->fill($payload);
                    $existingItem->save(); // 🔥 recalcula subtotal_aplicado en booted()
                } else {
                    $venta->items()->create($payload);
                }
            }

            $venta->refresh();
            $venta->loadMissing('items.servicio');

            // Activación auto si es gratis
            $totalCobroInicial = 0;
            if ($venta->items) {
                foreach ($venta->items as $item) {
                    if (! $item->servicio) continue;
                    $tipo = $item->servicio->tipo instanceof BackedEnum ? $item->servicio->tipo->value : $item->servicio->tipo;
                    if ($tipo === 'unico') {
                        $totalCobroInicial += (float) $item->subtotal_aplicado; // ✅ aplicado (con descuento)
                    }
                }
            }

            $tieneMetodoPago = false;
            if ($cliente->stripe_customer_id) {
                try {
                    Stripe::setApiKey(config('services.stripe.secret'));
                    if (app()->isLocal()) Stripe::setVerifySslCerts(false);
                    $cus = Customer::retrieve(['id' => $cliente->stripe_customer_id, 'expand' => ['invoice_settings.default_payment_method']]);
                    $defaultPM = $cus->invoice_settings->default_payment_method ?? null;
                    if ($recurrenteMetodo === 'tarjeta') {
                        $tieneMetodoPago = (bool) ($defaultPM && isset($defaultPM->card));
                    } elseif ($recurrenteMetodo === 'domiciliacion') {
                        $tieneMetodoPago = (bool) ($defaultPM && isset($defaultPM->sepa_debit));
                    }
                } catch (Exception $e) {}
            }

            if ($totalCobroInicial <= 0 && $tieneMetodoPago) {
                $venta->procesarCobroInicial(
                    fechaPago: now(),
                    metodoPago: 'suscripcion_directa',
                    paymentIntentId: null,
                    extraData: $formData
                );

                // ✅ ClienteActivado — creación usuario portal + email
                try {
                    $ventaFresh = $venta->fresh(['cliente']);
                    if ($ventaFresh->cliente) {
                        // Activar cliente y crear usuario portal
                        $activacionService = app(\App\Services\ClienteActivacionService::class);
                        $resultado = $activacionService->activarCliente(
                            $ventaFresh->cliente, 
                            'pago_online_completado'
                        );
                        
                        if (!$resultado['success']) {
                            \Illuminate\Support\Facades\Log::warning('Cliente no activado (ya tenía usuario)', [
                                'cliente_id' => $ventaFresh->cliente->id,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Error en activación de cliente: ' . $e->getMessage());
                }
            }

            // 4) PDF — Primera generación sin hash
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
                'hashFirma'        => null,
            ])->setPaper('a4');

            $fileName = 'contracts/contrato_' . $link->token . '_' . $signedAt->format('Ymd_His') . '.pdf';
            Storage::disk('public')->put($fileName, $pdf->output());

            // Calcular hash del PDF
            $hashFirma = hash_file('sha256', storage_path('app/public/' . $fileName));

            // Segunda generación con hash incluido
            $pdfFinal = Pdf::loadView('public.conversion.contract.master', [
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
                'hashFirma'        => $hashFirma,
            ])->setPaper('a4');

            Storage::disk('public')->put($fileName, $pdfFinal->output());

            $meta = $link->meta ?? [];
            $meta['pdf']       = $fileName;
            $meta['hash_firma'] = $hashFirma;
            $link->meta  = $meta;
            $link->save();

            // 5) Email
            try {
                $absolutePdfPath = storage_path('app/public/' . $fileName);

                // URL dinámica que recalcula el estado en el momento del clic
                $resumeUrl = route('conversion.resume', ['token' => $link->token]);

                Mail::to($cliente->email_contacto)->send(
                    new ContractSignedMail($lead, $absolutePdfPath, $resumeUrl, $venta->fresh(['items.servicio', 'cliente']))
                );
                LeadAutoEmailLog::create([
                    'lead_id' => $lead->id, 'estado' => 'firmado', 'intento' => 1, 'template_identifier' => 'contract_signed',
                    'subject' => 'Contrato firmado', 'scheduled_at' => now(), 'sent_at' => now(), 'status' => 'sent',
                    'triggered_by_user_id' => 9999, 'trigger_source' => 'firma_contrato'
                ]);

                // ✅ Notificación al comercial cuando cliente firma
                try {
                    if ($lead->asignado_id) {
                        $comercial = \App\Models\User::find($lead->asignado_id);
                        if ($comercial) {
                            \Filament\Notifications\Notification::make()
                                ->title('✍️ Contrato firmado')
                                ->body("El cliente {$cliente->razon_social} ha firmado el contrato.")
                                ->success()
                                ->sendToDatabase($comercial);
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('No se pudo notificar al comercial por firma: ' . $e->getMessage());
                }
            } catch (Throwable $e) {
                Log::error("ERROR enviando ContractSignedMail: " . $e->getMessage());
            }

            $lead->estado = LeadEstadoEnum::CONVERTIDO_FIRMADO;
            $lead->save();

            // Comentario
            try {
                $meta = $link->meta ?? [];
                if (empty($meta['comentario_firma_creado_at'])) {
                    $lead->comentarios()->create([
                        'user_id' => 9999, 'contenido' => '✅ Contrato firmado. PDF enviado. IP: ' . $request->ip()
                    ]);
                    $meta['comentario_firma_creado_at'] = now()->toDateTimeString();
                    $link->meta = $meta;
                    $link->save();
                }
            } catch (\Throwable $e) {}
        });

    } catch (Throwable $e) {
        Log::error('CRITICAL ERROR en sign(): ' . $e->getMessage());
        throw new HttpException(500, 'Ha ocurrido un error al firmar.');
    }

    $link->refresh();

    // Redirección lógica
    $venta = Venta::with('items.servicio')->find($link->meta['existing_venta_id'] ?? null);
    $formData = $link->meta['form_data'] ?? [];
    $tienePagoInicialReal = $this->tienePagoInicialReal($venta, $formData);

    if ($tienePagoInicialReal) {
        return redirect()->route('conversion.pago-inicial', ['token' => $link->token]);
    }

    $itemsRecurrentes = $venta?->items?->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente') ?? collect();
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
            'pago_inicial_metodo' => 'nullable|in:stripe,transferencia',
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
        $recurrenteMetodo = $data['recurrente_metodo'] ?? ($link->meta['recurrente_metodo'] ?? null);
        if ($recurrenteMetodo) {
            return redirect()->route('conversion.pago-recurrente', ['token' => $link->token]);
        }

        // 3) Si no hay nada más, finished
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
        $lead     = $link->lead;
        $cliente  = $venta->cliente;

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

        // EMAIL PASO 2: SOLO INSTRUCCIONES DE PAGO INICIAL
        if ($data['pago_inicial_metodo'] === 'transferencia' && $cliente) {
            try {
                // Idempotencia (por lead + venta)
                $yaEnviado = LeadAutoEmailLog::where('lead_id', $lead->id)
                    ->where('template_identifier', 'pago_inicial_transferencia')
                    ->where('status', 'sent')
                    ->where('body_preview', 'like', '%venta_id='.$venta->id.'%')
                    ->exists();

                if (! $yaEnviado) {

                    // IVA (igual que en finished)
                    $cpCliente   = $formData['cp'] ?? ($cliente->codigo_postal ?? '');
                    $provCliente = $formData['provincia'] ?? ($cliente->provincia ?? '');
                    $porcentajeIva = Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
                    $factorIva     = 1 + ($porcentajeIva / 100);

                    // total base únicos > 0
                    $venta->loadMissing('items.servicio');
                    $baseUnico = $venta->items
                        ->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'unico')
                        ->sum(fn ($i) => (float) ($i->subtotal_aplicado ?? 0));

                    $importeTotal = round($baseUnico * $factorIva, 2);

                    // IBAN empresa
                    $ibanEmpresa = null;
                    foreach (['empresa_banco_iban', 'empresa_iban_transferencias', 'iban_transferencias', 'iban_empresa', 'empresa_cuenta_bancaria'] as $k) {
                        $ibanEmpresa = DB::table('variables_configuracion')->where('nombre_variable', $k)->value('valor_variable');
                        if ($ibanEmpresa) break;
                    }

                    $concepto = "VENTA {$venta->id}" . ($cliente?->razon_social ? " - {$cliente->razon_social}" : '');

                    $resumeUrl = route('conversion.pago-inicial', ['token' => $link->token]);

                    Mail::to($cliente->email_contacto)->send(
                        new \App\Mail\PagoInicialTransferenciaMail(
                            lead: $lead,
                            venta: $venta->fresh(['items.servicio', 'cliente']),
                            importeTotal: $importeTotal,
                            porcentajeIva: $porcentajeIva,
                            iban: $ibanEmpresa,
                            concepto: $concepto,
                            resumeUrl: $resumeUrl,
                        )
                    );

                    LeadAutoEmailLog::create([
                        'lead_id'              => $lead->id,
                        'estado'               => 'firmado',
                        'intento'              => 1,
                        'template_identifier'  => 'pago_inicial_transferencia',
                        'subject'              => 'Datos para realizar la transferencia',
                        'body_preview'         => 'pago_inicial_transferencia venta_id='.$venta->id,
                        'scheduled_at'         => now(),
                        'sent_at'              => now(),
                        'status'               => 'sent',
                        'mail_driver'          => config('mail.default'),
                        'triggered_by_user_id' => 9999,
                        'trigger_source'       => 'guardar_pago_inicial',
                    ]);
                }

            } catch (\Throwable $e) {
                Log::error("❌ Error enviando email transferencia en guardarPagoInicial: ".$e->getMessage(), [
                    'lead_id'  => $lead->id ?? null,
                    'venta_id' => $venta->id ?? null,
                    'token'    => $link->token ?? null,
                ]);
            }
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

        // 1. Recuperar Venta
        $venta = null;
        if (! empty($link->meta['existing_venta_id'])) {
            $venta = Venta::with('items.servicio', 'cliente')->find($link->meta['existing_venta_id']);
        }

        if (! $venta) {
            return redirect()->route('conversion.finished', ['token' => $link->token]);
        }

        $venta->loadMissing('items.servicio', 'cliente');

        // Filtramos items recurrentes de la DB
        $itemsRecurrentes = $venta->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

        if ($itemsRecurrentes->isEmpty()) {
            return redirect()->route('conversion.finished', ['token' => $link->token]);
        }

        // ✅ BLOQUEO GLOBAL (DB): si existe algún único bloqueante, el recurrente va diferido
        $esperaProyectoGlobal = $venta->items->contains(fn ($i) =>
            $i->servicio
            && $i->servicio->tipo->value === 'unico'
            && (bool) ($i->bloquea_recurrente ?? false)
        );

        // 2. Datos de pago inicial
        $pagoInicialMetodo = $venta->pago_inicial_metodo
            ?? ($link->meta['pago_inicial_metodo'] ?? 'stripe');

        // 3. Verificación Stripe
        $hasDefaultPM   = false;
        $defaultPmLabel = null;
        $defaultPmType  = null;
        $defaultPmBrand = null;
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

                if ($defaultPm) {
                    if (isset($defaultPm->card)) {
                        $hasDefaultPM = true;
                        $defaultPmType = 'card';

                        $defaultPmBrand = strtolower($defaultPm->card->brand ?? '');
                        $last4 = $defaultPm->card->last4 ?? '0000';

                        $brandTxt = $defaultPmBrand ? ucfirst($defaultPmBrand) : 'Card';
                        $defaultPmLabel = "{$brandTxt} •••• {$last4}";
                    } elseif (isset($defaultPm->sepa_debit)) {
                        $hasDefaultPM = true;
                        $defaultPmType = 'sepa';
                        $defaultPmBrand = 'sepa';
                        $defaultPmLabel = "SEPA •••• " . ($defaultPm->sepa_debit->last4 ?? '0000');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("pagoRecurrente: no se pudo leer default_payment_method: " . $e->getMessage());
            }
        }

        $pagoInicialCompletado = $venta->tienePagoInicialCompletado();

        // 4. CÁLCULOS VISUALES
        $hoy = now()->locale('es');
        $diasMes = $hoy->daysInMonth ?: 30;
        $diasRestantes = ($diasMes - $hoy->day) + 1;
        $primerCobroFecha = $hoy->copy()->addMonthNoOverflow()->startOfMonth();

        $totalRecurrenteMensual = 0.0;
        $totalPagarHoy = 0.0;

        $hayPromo = false;
        $listaVisual = [];
        $form = $link->meta['form_data'] ?? [];

        foreach ($itemsRecurrentes as $item) {
            $nombre = $item->nombre_personalizado ?? $item->servicio->nombre ?? 'Servicio';
            $qty = (float) $item->cantidad;

            // Precio mensual aplicado (sin IVA)
            $baseMensualItem = (float) $item->subtotal_aplicado;

            // Meta cobro
            $meta = $item->meta ?? [];
            $cobro = $meta['cobro_primer_mes'] ?? 'prorrata';
            if (!empty($meta['no_cobrar_primer_periodo'])) $cobro = 'gratis';

            // ✅ DIFERIDO: depende del bloqueo global (no de requiere_proyecto)
            $esDiferido = $esperaProyectoGlobal;

            // Detectar Descuento (para mostrar tachado)
            $promo = $meta['descuento'] ?? [];
            $tieneDesc = !empty($promo['aplicar']);
            $precioOriginal = 0;
            if ($tieneDesc) {
                $hayPromo = true;
                if (($promo['tipo']??'') === 'porcentaje') {
                    $val = (float)$promo['valor'];
                    if ($val < 100) $precioOriginal = $baseMensualItem / (1 - ($val/100));
                } else {
                    $precioOriginal = $baseMensualItem + (float)($promo['valor']??0);
                }
            }

            // Total mensual
            $totalRecurrenteMensual += $baseMensualItem;

            // Importe HOY
            $importeHoyItem = 0.0;

            if ($esDiferido) {
                $importeHoyItem = 0;
            } else {
                if ($cobro === 'gratis') {
                    $importeHoyItem = 0;
                } elseif ($cobro === 'completo') {
                    $importeHoyItem = $baseMensualItem;
                } else {
                    $importeHoyItem = ($baseMensualItem / $diasMes) * $diasRestantes;
                }
            }

            $totalPagarHoy += $importeHoyItem;

            $listaVisual[] = [
                'nombre' => $nombre . ($qty > 1 ? " (x$qty)" : ''),
                'base_mensual' => $baseMensualItem,
                'precio_original' => $precioOriginal,
                'cobro_tipo' => $esDiferido ? 'diferido' : $cobro,
                'promo_info' => $tieneDesc ? $promo : null,
                'promo_meses' => (int)($promo['meses'] ?? $item->descuento_duracion_meses ?? 0),
            ];
        }

        // 5. IVA
        $cp = $form['cp'] ?? $cliente->codigo_postal;
        $prov = $form['provincia'] ?? $cliente->provincia;
        $porcentajeIva = Cliente::getPorcentajeImpuesto($cp, $prov);
        $factorIva = 1 + ($porcentajeIva / 100);

        $totalRecurrenteMensualConIva = $totalRecurrenteMensual * $factorIva;
        $totalPagarHoyConIva = $totalPagarHoy * $factorIva;

        return view('public.conversion.pago-recurrente', [
            'link' => $link,
            'lead' => $link->lead,
            'venta' => $venta,
            'pagoInicialMetodo' => $pagoInicialMetodo,
            'pagoInicialCompletado' => $pagoInicialCompletado,
            'hasDefaultPM' => $hasDefaultPM,
            'defaultPmLabel' => $defaultPmLabel,
            'defaultPmType' => $defaultPmType,
            'defaultPmBrand' => $defaultPmBrand,

            'items' => $listaVisual,
            'totalMensual' => $totalRecurrenteMensualConIva,
            'totalPagarHoy' => $totalPagarHoyConIva,
            'esGratisHoy' => ($totalPagarHoy < 0.01),
            'esperaProyecto' => $esperaProyectoGlobal,
            'hayPromo' => $hayPromo,

            'porcentajeIva' => $porcentajeIva,
            'textoPrimerCobro' => '1 de ' . ucfirst($primerCobroFecha->translatedFormat('F')),
            'mesActual' => ucfirst($hoy->translatedFormat('F')),
            'diasRestantes' => $diasRestantes,
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

        // ✅ 1) Si ya existe una suscripción creada para esta venta -> tampoco tiene sentido repetir
        $subExistente = ClienteSuscripcion::query()
            ->where('venta_origen_id', $venta->id)
            ->whereNotNull('stripe_subscription_id')
            ->latest('id')
            ->first();

        if (($venta->estado?->value ?? null) === VentaEstadoEnum::COMPLETADA->value && $subExistente) {
            return redirect()->route('conversion.finished', ['token' => $link->token])
                ->with('success', 'La cuota mensual ya está configurada.');
        }

        if ($subExistente) {
            return redirect()->route('conversion.finished', ['token' => $link->token])
                ->with('success', 'La cuota mensual ya está configurada.');
        }

        // ✅ 2) Si no hay items recurrentes, esta pantalla no aplica
        $tieneRecurrente = $venta->items->contains(fn ($i) =>
            $i->servicio && $i->servicio->tipo->value === 'recurrente'
        );

        if (! $tieneRecurrente) {
            return redirect()->route('conversion.finished', ['token' => $link->token]);
        }

        $data = $request->validate([
            'recurrente_metodo' => 'required|in:tarjeta,domiciliacion',
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

        Log::info('guardarPagoRecurrente guardado', [
            'token' => $link->token,
            'metodo' => $metodo,
            'tarjeta_accion' => $meta['tarjeta_accion'] ?? null,
            'meta_recurrente_metodo_en_db' => data_get($link->fresh()->meta, 'recurrente_metodo'),
        ]);

        // Guardar preferencia local (solo UX)
        $venta->cliente->preferencia_pago_recurrente = $metodo;
        $venta->cliente->saveQuietly();

        // A) Domiciliación -> setup SEPA
        if ($metodo === 'domiciliacion') {
            return redirect()->route('stripe.setup-sepa', ['token' => $link->token]);
        }

        // B) Tarjeta -> decidir si usar existente o meter otra
        $cliente = $venta->cliente;

        if (($data['tarjeta_accion'] ?? null) === 'usar_otra') {
            return redirect()->route('stripe.setup-card', ['token' => $link->token]);
        }

        if (! $cliente->stripe_customer_id) {
            return redirect()->route('stripe.setup-card', ['token' => $link->token]);
        }

        $tieneTarjetaDefault = false;

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            if (app()->isLocal()) Stripe::setVerifySslCerts(false);

            $customer = Customer::retrieve([
                'id'     => $cliente->stripe_customer_id,
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPM = $customer->invoice_settings->default_payment_method ?? null;

            $tieneTarjetaDefault = (bool) ($defaultPM && isset($defaultPM->card));
        } catch (\Throwable $e) {
            Log::warning('No se pudo comprobar default_payment_method: ' . $e->getMessage());
        }

        if (! $tieneTarjetaDefault) {
            return redirect()->route('stripe.setup-card', ['token' => $link->token]);
        }

        return redirect()
            ->route('conversion.finished', ['token' => $link->token])
            ->with('recurrente_setup_success', true);
    }

    // =========================================================================
    // 🔥 FINISHED
    // =========================================================================
public function finished(string $token, Request $request)
{
    /** @var LeadConversionLink $link */
    $link = $request->attributes->get('conversion_link');
    if (! $link) abort(404);

    $lead = $link->lead;

    // 1) Blueprint (para pintar resumen precios)
    $blueprint = $link->meta['sale_blueprint'] ?? [];
    $services  = collect($blueprint['servicios'] ?? []);
    $form      = $link->meta['form_data'] ?? [];

    // 2) Venta + Cliente
    $venta = null;
    if (! empty($link->meta['existing_venta_id'])) {
        $venta = Venta::with('cliente', 'items.servicio')->find($link->meta['existing_venta_id']);
    }
    $cliente = $venta?->cliente;

    // 3) IVA
    $cp   = $form['cp'] ?? ($cliente?->codigo_postal ?? '');
    $prov = $form['provincia'] ?? ($cliente?->provincia ?? '');
    $porcentajeIva = Cliente::getPorcentajeImpuesto($cp, $prov);
    $factorIva     = 1 + ($porcentajeIva / 100);

    $toBool = static function ($v): bool {
        if (is_bool($v)) return $v;
        if ($v === null || $v === '') return false;
        $parsed = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? (bool) $v;
    };

    /**
     * ✅ BLOQUEO GLOBAL (DB/Blueprint):
     * si algún ÚNICO bloquea_recurrente => TODOS los recurrentes quedan diferidos.
     *
     * - editable => usa bloquea_recurrente
     * - no editable => usa servicio_bloquea_recurrente (o bloquea_recurrente legacy)
     * - fallback legacy si no existen campos => requiere_proyecto / servicio_requiere_proyecto
     */
    $esperaProyecto = false;

    if ($venta) {
        $esperaProyecto = $venta->items->contains(function ($i) {
            if (! $i->servicio) return false;
            if (($i->servicio->tipo?->value ?? null) !== 'unico') return false;

            $itemBloquea = (bool) ($i->bloquea_recurrente ?? false);
            $svcBloquea  = (bool) ($i->servicio->bloquea_recurrente ?? false);

            if ($itemBloquea || $svcBloquea) return true;

            // Legacy: si antes se usaba "requiere proyecto" como bloqueo
            if (!isset($i->bloquea_recurrente) && !isset($i->servicio->bloquea_recurrente)) {
                $esEditable = (bool) ($i->servicio->es_editable ?? false);
                return $esEditable
                    ? (bool) ($i->requiere_proyecto ?? false)
                    : (bool) ($i->servicio->requiere_proyecto_activacion ?? false);
            }

            return false;
        });
    } else {
        $esperaProyecto = $services->contains(function ($s) use ($toBool) {
            if (($s['tipo'] ?? '') !== 'unico') return false;

            $esEditable = $toBool($s['es_editable'] ?? false);

            $flag = $esEditable
                ? $toBool($s['bloquea_recurrente'] ?? false)
                : $toBool($s['servicio_bloquea_recurrente'] ?? ($s['bloquea_recurrente'] ?? false));

            if (!isset($s['bloquea_recurrente']) && !isset($s['servicio_bloquea_recurrente'])) {
                $flag = $esEditable
                    ? $toBool($s['requiere_proyecto'] ?? false)
                    : $toBool($s['servicio_requiere_proyecto'] ?? false);
            }

            return $flag;
        });
    }

    // 4) Resumen servicios (con IVA) + cobro_primer_mes + promo
    $resumenServicios = $services->map(function ($s) use ($factorIva, $toBool) {
        $tipo = (string) ($s['tipo'] ?? 'unico');
        $qty  = (int) ($s['unidades'] ?? 1);
        $qty  = $qty > 0 ? $qty : 1;

        $baseFinal = (float) ($s['subtotal_final'] ?? $s['subtotal_base'] ?? 0);

        $baseOriginal = (float) ($s['subtotal_base'] ?? 0);
        if ($baseOriginal <= 0) {
            $precioBaseUnit = (float) ($s['precio_base_original'] ?? $s['precio_base'] ?? 0);
            $baseOriginal = $precioBaseUnit > 0 ? ($precioBaseUnit * $qty) : $baseFinal;
        }

        $cobro = $s['cobro_primer_mes'] ?? 'prorrata';
        if (! empty($s['no_cobrar_primer_periodo']) && $toBool($s['no_cobrar_primer_periodo'])) {
            $cobro = 'gratis';
        }

        $descTxt = null;
        $promo = $s['descuento'] ?? [];
        if (! empty($promo['aplicar'])) {
            $val = (float) ($promo['valor'] ?? 0);
            $tipoPromo = (string) ($promo['tipo'] ?? 'porcentaje');
            $suffix = ($tipoPromo === 'porcentaje') ? '%' : '€';
            $descTxt = "-{$val}{$suffix}";
        }

        return [
            'servicio_id' => $s['servicio_id'] ?? null,
            'nombre' => $s['nombre'] ?? 'Servicio',
            'cantidad' => $qty,
            'tipo' => $tipo,

            'precio_mensual' => round($baseFinal * $factorIva, 2),
            'precio_original' => round($baseOriginal * $factorIva, 2),

            'raw_subtotal' => $baseFinal,

            'cobro_primer_mes' => $cobro,
            'no_cobrar_primer_periodo' => $toBool($s['no_cobrar_primer_periodo'] ?? false),

            'texto_descuento' => $descTxt,
            'promo_meses' => (int) ($promo['meses'] ?? $s['descuento_duracion_meses'] ?? 0),

            // ✅ Flags para contrato/resumen
            'es_editable' => $toBool($s['es_editable'] ?? false),
            'requiere_proyecto' => $toBool($s['requiere_proyecto'] ?? false),

            // bloqueo explícito (nuevo)
            'bloquea_recurrente' => $toBool($s['bloquea_recurrente'] ?? false),
            'servicio_bloquea_recurrente' => $toBool($s['servicio_bloquea_recurrente'] ?? false),

            // proyecto (no bloqueo por sí solo, salvo legacy)
            'servicio_requiere_proyecto' => $toBool($s['servicio_requiere_proyecto'] ?? false),

            // badges
            'es_tarifa_principal' => $toBool($s['es_tarifa_principal'] ?? false),
        ];
    });

    // 5) Separar
    $listUnicos = $resumenServicios->where('tipo', 'unico');
    $listRecurrentes = $resumenServicios->where('tipo', 'recurrente');

    // --- PAGO ÚNICO ---
    $totalPagoInicial = (float) $listUnicos->sum('precio_mensual'); // con IVA
    $pagoInicialRealizado = ($totalPagoInicial > 0 && $venta) ? $venta->tienePagoInicialCompletado() : false;

    // --- RECURRENTE ---
    $tieneRecurrente = $listRecurrentes->isNotEmpty();
    $totalRecurrenteMensual = (float) $listRecurrentes->sum('precio_mensual');     // con IVA
    $totalRecurrenteOriginal = (float) $listRecurrentes->sum('precio_original');  // con IVA

    $prorrateo = null;
    $detallePromo = null;

    if ($tieneRecurrente && ! $esperaProyecto) {
        $hoy = now()->locale('es');
        $diasMes = $hoy->daysInMonth ?: 30;
        $diasRestantes = ($diasMes - $hoy->day) + 1;
        $primerCobroFecha = $hoy->copy()->addMonthNoOverflow()->startOfMonth();

        $totalPrimerCobroSinIva = 0.0;

        $hayCompletos = false;
        $hayProrratas = false;
        $hayGratis    = false;

        foreach ($listRecurrentes as $item) {
            $cobro = $item['cobro_primer_mes'] ?? 'prorrata';
            $baseItem = (float) ($item['raw_subtotal'] ?? 0);

            if ($cobro === 'gratis') {
                $hayGratis = true;
                continue;
            }

            if ($cobro === 'completo') {
                $hayCompletos = true;
                $totalPrimerCobroSinIva += $baseItem;
                continue;
            }

            $hayProrratas = true;
            $totalPrimerCobroSinIva += ($baseItem / $diasMes) * $diasRestantes;
        }

        $totalPrimerCobroConIva = round($totalPrimerCobroSinIva * $factorIva, 2);

        $tipoPrimerCobro = 'gratis';
        if ($totalPrimerCobroConIva >= 0.01) {
            if ($hayCompletos && $hayProrratas) $tipoPrimerCobro = 'mixto';
            elseif ($hayCompletos) $tipoPrimerCobro = 'completo';
            else $tipoPrimerCobro = 'prorrata';
        }

        $prorrateo = [
            'tipo'       => $tipoPrimerCobro,
            'importe'    => number_format($totalPrimerCobroConIva, 2, ',', '.'),
            'es_gratis'  => ($totalPrimerCobroConIva < 0.01),
            'mes_actual' => ucfirst($hoy->translatedFormat('F')),
            'siguiente'  => ucfirst($primerCobroFecha->translatedFormat('F')),
        ];

        $itemRef = $listRecurrentes->first();
        $mesesPromo = (int) ($itemRef['promo_meses'] ?? 0);

        if (! empty($itemRef['texto_descuento'])) {
            $cobraAlgoHoy = ($tipoPrimerCobro !== 'gratis');
            $mesesRestantes = $cobraAlgoHoy ? max(0, $mesesPromo - 1) : $mesesPromo;

            if ($mesesRestantes > 0) {
                $inicioPromo = $primerCobroFecha->copy()->startOfMonth();
                $finPromo = $inicioPromo->copy()->addMonthsNoOverflow($mesesRestantes - 1)->startOfMonth();

                $rangoFechas = ucfirst($inicioPromo->translatedFormat('F Y'));
                if ($mesesRestantes > 1) {
                    $rangoFechas .= " a " . ucfirst($finPromo->translatedFormat('F Y'));
                }

                $fechaNormal = $finPromo->copy()->addMonthNoOverflow()->startOfMonth();

                $detallePromo = [
                    'texto'        => $itemRef['texto_descuento'],
                    'duracion_txt' => $mesesRestantes . ($mesesRestantes === 1 ? ' mes' : ' meses'),
                    'rango'        => $rangoFechas,
                    'precio_normal'=> number_format($totalRecurrenteOriginal, 2, ',', '.'),
                    'fecha_normal' => ucfirst($fechaNormal->translatedFormat('F Y')),
                ];
            }
        }
    }

    $nombreServicioRecurrente = $listRecurrentes->first()['nombre'] ?? 'Suscripción';
    $pdfUrl = ! empty($link->meta['pdf'])
        ? Storage::disk('public')->url($link->meta['pdf'])
        : null;

    $pagoInicialMetodoRaw = data_get($venta, 'pago_inicial_metodo')
        ?? data_get($link->meta, 'pago_inicial_metodo')
        ?? 'stripe';

    $esTransferencia = ($pagoInicialMetodoRaw === 'transferencia');

   // ✅ Datos transferencia desde variables de configuración (sin tocar estilos, solo datos)
            $transferencia = null;
            if ($esTransferencia && ($listUnicos->isNotEmpty()) && ! $pagoInicialRealizado) {

                $pick = static function (array $keys, $default = null) {
                    foreach ($keys as $k) {
                        $v = ConfiguracionService::get($k, null);
                        if (is_string($v)) $v = trim($v);
                        if (!empty($v)) return $v;
                    }
                    return $default;
                };

                $iban = $pick([
                    'empresa_banco_iban',
                    'empresa_iban_transferencias',
                    'iban_transferencias',
                    'iban',
                    'cuenta_bancaria_iban',
                ]);

                $beneficiario = $pick([
                    'empresa_titular_transferencias',
                    'titular_transferencias',
                    'empresa_razon_social',
                    'empresa_nombre',
                ], 'AsesorFy');

                $banco = $pick([
                    'empresa_banco_transferencias',
                    'banco_transferencias',
                    'empresa_banco',
                    'banco',
                ]);

                $swift = $pick([
                    'empresa_swift_transferencias',
                    'swift_transferencias',
                    'empresa_swift',
                    'swift',
                ]);

                // ✅ DNI/CIF para el concepto
                $doc = trim((string) ($form['cif'] ?? $form['dni'] ?? ''));
                $doc = $doc !== '' ? strtoupper($doc) : null;

                // ✅ Concepto: "VENTA {id} - {DNI/CIF}" (si no hay venta: "CONVERSION {token} - {DNI/CIF}")
                if ($venta) {
                    $concepto = 'VENTA ' . $venta->id;
                } else {
                    $concepto = 'CONVERSION ' . ($link->token ?? $token);
                }

                if ($doc) {
                    $concepto .= ' - ' . $doc;
                }

                $transferencia = [
                    'iban' => $iban,

                    // ✅ nuevo nombre
                    'beneficiario' => $beneficiario,

                    // ✅ compat (por si algo viejo mira 'titular')
                    'titular' => $beneficiario,

                    'banco' => $banco,
                    'swift' => $swift,
                    'concepto' => $concepto,
                ];
                }
                    if ($cliente && $cliente->estado === \App\Enums\ClienteEstadoEnum::PENDIENTE) {
                        $cliente->update(['estado' => \App\Enums\ClienteEstadoEnum::PENDIENTE_ASIGNACION]);
                    }

    return view('public.conversion.finished', [
        'lead' => $lead,
        'form' => $form,
        'pdfUrl' => $pdfUrl,

        'resumenServicios' => $resumenServicios,

        'pagoInicial' => [
            'existe'  => $listUnicos->isNotEmpty(),
            'importe' => number_format($totalPagoInicial, 2, ',', '.'),
            'metodo'  => $esTransferencia ? 'Transferencia' : 'Tarjeta',
            'pagado'  => $pagoInicialRealizado,
            'es_transferencia' => $esTransferencia,
        ],

        'transferencia' => $transferencia,

        'recurrente' => [
            'existe'        => $tieneRecurrente,
            'total_mes'     => number_format($totalRecurrenteMensual, 2, ',', '.'),
            'es_diferido'   => $esperaProyecto,
            'prorrateo'     => $prorrateo,
            'detalle_promo' => $detallePromo,
            'nombre'        => $nombreServicioRecurrente,
        ],

        'cardInfo' => null,
    ]);
}



    private function redirectStripeSetup($preferencia, $token)
    {
        $route = $preferencia === 'domiciliacion' ? 'stripe.setup-sepa' : 'stripe.setup-card';
        return redirect()->route($route, ['token' => $token]);
    }

    /**
     * ✅ Calcula el IMPORTE REAL del pago inicial (solo servicios UNICOS) + IVA.
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

    private function parseMoneyOrNumber(mixed $raw): float
{
    if ($raw === null) return 0.0;

    if (is_string($raw)) {
        $raw = trim($raw);
        $raw = str_replace(['€', ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
    }

    return is_numeric($raw) ? (float) $raw : 0.0;
}

private function mapDescuentoColumns(array $s): array
{
    // Prioridad: nuevo formato blueprint -> descuento{}
    $aplicar = (bool) data_get($s, 'descuento.aplicar', false);

    if (! $aplicar) {
        return ['tipo' => null, 'valor' => null, 'meses' => null, 'obs' => null];
    }

    $tipo  = (string) (data_get($s, 'descuento.tipo') ?? '');
    $valor = data_get($s, 'descuento.valor');
    $meses = data_get($s, 'descuento.meses');

    // Fallback legacy si algún día viene plano
    if ($tipo === '') {
        $tipo = (string) ($s['descuento_tipo'] ?? '');
    }
    if ($valor === null) {
        $valor = $s['descuento_valor'] ?? null;
    }
    if ($meses === null) {
        $meses = $s['descuento_duracion_meses'] ?? null;
    }

    $tipo = trim(mb_strtolower($tipo));
    if (! in_array($tipo, ['porcentaje', 'fijo', 'precio_final'], true)) {
        $tipo = 'porcentaje';
    }

    $valorNum = $this->parseMoneyOrNumber($valor);
    $mesesInt = is_null($meses) ? null : (int) $meses;

    if ($valorNum <= 0) {
        return ['tipo' => null, 'valor' => null, 'meses' => null, 'obs' => null];
    }

    return [
        'tipo'  => $tipo,
        'valor' => $valorNum,
        'meses' => ($mesesInt && $mesesInt > 0) ? $mesesInt : null,
        'obs'   => null,
    ];
}



    /**
     * Redirige dinámicamente según el estado actual del proceso de conversión
     */
    public function resume(string $token): RedirectResponse
    {
        $link = LeadConversionLink::where('token', $token)->firstOrFail();
        $venta = Venta::where('lead_id', $link->lead_id)->latest()->first();

        $resumeUrl = $this->determinarResumeUrl($link, $venta);

        return redirect($resumeUrl);
    }

    /**
     * Determina la URL correcta para "Retomar mi alta" según el estado actual
     */
    private function determinarResumeUrl(LeadConversionLink $link, ?Venta $venta): string
    {
        $meta = $link->meta ?? [];
        $formData = $meta['form_data'] ?? [];
        
        // 1. Si no hay venta, volver al inicio
        if (!$venta) {
            return route('conversion.show', ['token' => $link->token]);
        }
        
        // 2. Verificar si tiene pago inicial pendiente
        $tienePagoInicialReal = $this->tienePagoInicialReal($venta, $formData);
        
        if ($tienePagoInicialReal && !$venta->tienePagoInicialCompletado()) {
            return route('conversion.pago-inicial', ['token' => $link->token]);
        }
        
        // 3. Verificar si tiene recurrentes sin configurar
        $venta->loadMissing('items.suscripcion', 'items.servicio');
        $itemsRecurrentes = $venta->items->filter(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');
        
        if ($itemsRecurrentes->isNotEmpty()) {
            // Revisar si TODOS los recurrentes tienen suscripción CREADA
            $todosTienenSuscripcion = $itemsRecurrentes->every(function ($item) {
                return $item->suscripcion !== null; // ✅ Verifica si el objeto existe
            });
            
            if (!$todosTienenSuscripcion) {
                return route('conversion.pago-recurrente', ['token' => $link->token]);
            }
        }
        
        // 4. Todo completado → finished
        return route('conversion.finished', ['token' => $link->token]);
    }

    /**
     * Construir observaciones incluyendo datos del representante y estado sociedad
     */
    private function construirObservaciones(array $formData): ?string
    {
        $observaciones = [];
        
        // Observaciones del formulario
        if (!empty($formData['observaciones'])) {
            $observaciones[] = $formData['observaciones'];
        }
        
        // Estado de la sociedad
        if (!empty($formData['estado_sociedad'])) {
            $estadoTexto = $formData['estado_sociedad'] === 'constituida' 
                ? 'Sociedad constituida (con CIF)' 
                : 'Sociedad en constitución (sin CIF)';
            $observaciones[] = "Estado: {$estadoTexto}";
        }
        
        // Datos del representante (si son diferentes de los del contacto principal)
        $nombreRep = trim($formData['nombre_representante'] ?? '');
        $apellidosRep = trim($formData['apellidos_representante'] ?? '');
        $nombreContacto = trim($formData['nombre'] ?? '');
        $apellidosContacto = trim($formData['apellidos'] ?? '');
        
        if ($nombreRep && $apellidosRep) {
            // Si el representante es diferente al contacto principal
            if ($nombreRep !== $nombreContacto || $apellidosRep !== $apellidosContacto) {
                $observaciones[] = "Representante legal: {$nombreRep} {$apellidosRep}";
            }
        }
        
        return !empty($observaciones) ? implode(' | ', $observaciones) : null;
    }
}
