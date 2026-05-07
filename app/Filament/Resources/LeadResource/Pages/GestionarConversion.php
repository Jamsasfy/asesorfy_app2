<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\LeadResource;
use App\Mail\LeadConversionLinkMail;
use App\Models\Lead;
use App\Models\LeadAutoEmailLog;
use App\Models\LeadConversionLink;
use App\Models\Servicio;
use App\Models\TipoCliente;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class GestionarConversion extends Page
{
    protected static string $resource = LeadResource::class;

    protected string $view = 'filament.resources.leads.partials.gestionar-conversion';

    public $record;

    public Lead $lead;

    /** @var \Illuminate\Support\Collection<int,TipoCliente> */
    public Collection $tiposCliente;

    /** @var \Illuminate\Support\Collection<int,Servicio> */
    public Collection $servicios;

    public ?int $tipoClienteId = null;

    /**
     * Seguridad / confirmación email
     */
    public ?string $emailDestino = null;
    public ?string $emailConfirm = null;

    /**
     * Cancelación conversión
     */
    public ?string $cancelReason = null;

    /**
     * items[x]:
     * - servicio_id
     * - tipo ('unico'|'recurrente')
     * - es_editable (bool)
     *
     * ✅ REGLA NUEVA:
     * - Proyecto SIEMPRE viene del Servicio (requiere_proyecto_activacion)
     * - Ya no se decide manualmente en UI
     *
     * - servicio_requiere_proyecto (bool) ✅ fuente de verdad (siempre)
     * - requiere_proyecto (bool) ⚠️ legacy/compat (NO se usa, siempre false)
     *
     * - bloquea_recurrente (bool) ✅ (para prorrata/cobro diferido)
     * - nombre_personalizado
     * - precio_base (unitario sin IVA)
     * - cantidad
     *
     * ✅ CAMBIO: Sustituimos bool 'no_cobrar_primer_periodo' por 'cobro_primer_mes'
     * - cobro_primer_mes (string): 'prorrata' | 'completo' | 'gratis'
     *
     * - aplicar_descuento (bool)
     * - descuento_tipo ('porcentaje'|'fijo'|'precio_final'|null)
     * - descuento_valor (string|null)
     * - descuento_duracion_meses (int|null)   ✅ SOLO recurrente
     *
     * - subtotal_base
     * - precio_final_unit
     * - subtotal_final
     */
    public array $items = [];

    public ?array $conversionInfo = null; // token, url, last_sent_at, last_sent_to, etc.

    public function mount($record): void
    {
        $this->lead = Lead::findOrFail($record);

        // B5-L9: ownership check — misma lógica que getEloquentQuery()
        $user = auth()->user();
        if ($user && $user->hasRole('comercial') && ! $user->hasRole('super_admin')) {
            abort_unless(
                $this->lead->asignado_id === $user->id,
                403,
                'No tienes acceso a este lead.'
            );
        }

        $this->tiposCliente = TipoCliente::query()
            ->orderBy('nombre')
            ->get();

        $this->servicios = Servicio::query()
            ->orderBy('nombre')
            ->get();

        $this->tipoClienteId = $this->lead->cliente?->tipo_cliente_id
            ?? $this->tiposCliente->first()?->id;

        // ✅ Email destino (ajusta si tu Lead usa otro campo)
        $this->emailDestino =
            $this->lead->email
            ?? $this->lead->email_contacto
            ?? $this->lead->cliente?->email_contacto
            ?? null;

        // ✅ Cargamos info del link (incluye blueprint)
        $this->loadConversionInfo();

        // 🔥 Si ya está enviada / finalizada: reconstruimos items desde blueprint
        $estado = $this->lead->estado?->value ?? null;

        if (in_array($estado, [
            LeadEstadoEnum::CONVERTIDO->value,
            LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS->value,
            LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA->value,
            LeadEstadoEnum::CONVERTIDO_FIRMADO->value,
            LeadEstadoEnum::CONVERTIDO_CORRECCION->value,
        ], true)) {
            $serviciosBlueprint = data_get($this->conversionInfo, 'blueprint.servicios', []);

            if (is_array($serviciosBlueprint) && ! empty($serviciosBlueprint)) {
                $this->items = $this->mapBlueprintServiciosToItems($serviciosBlueprint);
                return;
            }

            return;
        }

        // Si no hay propuesta enviada → flujo normal
        $this->addItem();
    }

    /**
     * 🔒 Conversión bloqueada: no se debe reenviar / reiniciar / cancelar
     */
    private function conversionBloqueada(): bool
    {
        return ($this->lead->estado?->value ?? null) === LeadEstadoEnum::CONVERTIDO_FIRMADO->value;
    }

    private function mapBlueprintServiciosToItems(array $serviciosBlueprint): array
    {
        return collect($serviciosBlueprint)
            ->filter(fn ($s) => is_array($s))
            ->map(function (array $s) {
                $servicioId = (int) ($s['servicio_id'] ?? $s['id'] ?? 0);
                $servicioId = $servicioId > 0 ? $servicioId : null;

                /** @var \App\Models\Servicio|null $svc */
                $svc = $servicioId ? $this->servicios->firstWhere('id', $servicioId) : null;

                $tipo = (string) ($s['tipo'] ?? ($svc?->tipo?->value ?? 'unico'));

                $cantidad = (int) ($s['unidades'] ?? $s['cantidad'] ?? 1);
                $cantidad = $cantidad > 0 ? $cantidad : 1;

                $esEditable = (bool) ($s['es_editable'] ?? ($svc?->es_editable ?? false));

                $nombrePersonalizado = null;
                if ($esEditable) {
                    $nombrePersonalizado = $s['nombre']
                        ?? data_get($s, 'nombre_personalizado')
                        ?? $svc?->nombre;
                }

                // Base vs final
                $precioBaseOriginal = (float) ($s['precio_base_original'] ?? $s['precio_base'] ?? 0);
                $precioFinalUnit    = (float) ($s['precio_final_unit'] ?? $s['precio_base'] ?? $precioBaseOriginal);

                $subtotalBase  = (float) ($s['subtotal_base'] ?? round($precioBaseOriginal * $cantidad, 2));
                $subtotalFinal = (float) ($s['subtotal_final'] ?? round($precioFinalUnit * $cantidad, 2));

                $dtoAplicar = (bool) data_get($s, 'descuento.aplicar', false);
                $dtoTipo    = data_get($s, 'descuento.tipo');
                $dtoValor   = data_get($s, 'descuento.valor');
                $dtoMeses   = data_get($s, 'descuento.meses');

                // ✅ Proyecto: prioriza blueprint si existe (histórico), si no, servicio actual
                $servicioRequiereProyecto =
                    array_key_exists('servicio_requiere_proyecto', $s)
                        ? (bool) $s['servicio_requiere_proyecto']
                        : (bool) ($svc?->requiere_proyecto_activacion ?? false);

                // ⚠️ Legacy: ya no se usa manualmente
                $requiereProyecto = false;

                // ✅ bloquea recurrente (blueprint manda; si no, Servicio)
                $bloqueaRecurrente = (bool) ($s['bloquea_recurrente'] ?? ($svc?->bloquea_recurrente ?? false));

                // ✅ Selector cobro
                $cobroPrimerMes = $s['cobro_primer_mes'] ?? 'prorrata';
                $legacyNoCobrar = (bool) ($s['no_cobrar_primer_periodo'] ?? false);
                if ($legacyNoCobrar) {
                    $cobroPrimerMes = 'gratis';
                }

                if ($tipo !== 'recurrente') {
                    $cobroPrimerMes = 'prorrata';
                }

                return [
                    'servicio_id' => $servicioId,
                    'tipo' => $tipo,

                    'es_editable' => $esEditable,

                    // ✅ Fuente de verdad
                    'servicio_requiere_proyecto' => $servicioRequiereProyecto,
                    // ⚠️ legacy
                    'requiere_proyecto' => $requiereProyecto,

                    'bloquea_recurrente' => $bloqueaRecurrente,

                    'nombre_personalizado' => $nombrePersonalizado,

                    'precio_base' => $precioBaseOriginal,
                    'cantidad' => $cantidad,

                    'cobro_primer_mes' => $cobroPrimerMes,
                    'no_cobrar_primer_periodo' => ($cobroPrimerMes === 'gratis'),

                    'aplicar_descuento' => $dtoAplicar,
                    'descuento_tipo' => $dtoAplicar ? $dtoTipo : null,
                    'descuento_valor' => $dtoAplicar ? (is_null($dtoValor) ? null : (string) $dtoValor) : null,
                    'descuento_duracion_meses' => ($tipo === 'recurrente' && $dtoAplicar)
                        ? (is_null($dtoMeses) ? null : (int) $dtoMeses)
                        : null,

                    'subtotal_base' => round($subtotalBase, 2),
                    'precio_final_unit' => round($precioFinalUnit, 2),
                    'subtotal_final' => round($subtotalFinal, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    private function loadConversionInfo(): void
    {
        $estado = $this->lead->estado?->value ?? null;

        $q = LeadConversionLink::query()
            ->where('lead_id', $this->lead->id)
            ->latest('id');

        if ($estado !== LeadEstadoEnum::CONVERTIDO_FIRMADO->value) {
            $q->active();
        }

        $link = $q->first();

        if (! $link) {
            $this->conversionInfo = null;
            return;
        }

        $meta = $link->meta ?? [];
        $expiresAt = $link->expires_at ? Carbon::parse($link->expires_at) : null;

        $isUsed    = ! empty($link->used_at);
        $isRevoked = ! empty(data_get($meta, 'revoked_at'));
        $isExpiredByTime = $expiresAt ? $expiresAt->isPast() : false;

        $isExpired = $isUsed || $isRevoked || $isExpiredByTime || ($estado === LeadEstadoEnum::CONVERTIDO_FIRMADO->value);

        $canOpenPublic = ! $isExpired;

        $this->conversionInfo = [
            'token'        => $link->token,
            'url'          => $canOpenPublic ? route('conversion.show', ['token' => $link->token]) : null,

            'created_at'   => optional($link->created_at)->toDateTimeString(),
            'expires_at'   => optional($expiresAt)->toDateTimeString(),

            'is_used'      => $isUsed,
            'is_revoked'   => $isRevoked,
            'is_expired'   => $isExpired,

            'last_sent_to' => $meta['last_sent_to'] ?? null,
            'last_sent_at' => $meta['last_sent_at'] ?? null,

            'blueprint'    => $meta['sale_blueprint'] ?? null,
            'pdf'          => $meta['pdf'] ?? null,

            'revoked_at'   => $meta['revoked_at'] ?? null,
            'revoked_reason' => $meta['revoked_reason'] ?? null,
        ];
    }

    private function buildItemsServiciosBlueprint(): array
    {
        return collect($this->items)
            ->filter(fn ($it) => ! empty($it['servicio_id']))
            ->map(function ($it) {

                /** @var Servicio|null $svc */
                $svc = $this->servicios->firstWhere('id', (int) $it['servicio_id'])
                    ?? Servicio::find((int) $it['servicio_id']);

                if (! $svc) return null;

                // Tipo robusto (enum/string)
                $tipo = $it['tipo'] ?? null;
                if ($tipo instanceof \BackedEnum) {
                    $tipo = $tipo->value;
                } elseif ($tipo instanceof \UnitEnum) {
                    $tipo = $tipo->name;
                } elseif ($tipo === null) {
                    $tipo = ($svc->tipo instanceof \BackedEnum) ? $svc->tipo->value : (string) ($svc->tipo ?? 'unico');
                }
                $tipo = (string) $tipo;

                $nombreServicio = (string) ($svc->nombre ?? '');
                $nombrePersonalizado = (string) ($it['nombre_personalizado'] ?? '');
                $esEditable = (bool) ($it['es_editable'] ?? ($svc->es_editable ?? false));

                $nombreMostrado = $esEditable
                    ? (trim($nombrePersonalizado) !== '' ? trim($nombrePersonalizado) : $nombreServicio)
                    : $nombreServicio;

                $cantidad = (int) ($it['cantidad'] ?? 1);
                $cantidad = $cantidad <= 0 ? 1 : $cantidad;

                $precioBaseOriginal = (float) ($it['precio_base'] ?? $svc->precio_base ?? 0);

                $precioFinalUnit = (float) ($it['precio_final_unit'] ?? $precioBaseOriginal);
                $precioParaContrato = $precioFinalUnit;

                $subtotalFinal = (float) ($it['subtotal_final'] ?? ($precioParaContrato * $cantidad));

                $nombreDetector = strtolower($nombreServicio . ' ' . $nombreMostrado);

                // ✅ Proyecto: SIEMPRE por servicio
                $servicioRequiereProyecto = (bool) ($svc->requiere_proyecto_activacion ?? false);

                // ⚠️ legacy manual no se usa
                $requiereProyectoEditable = false;

                $bloqueaRecurrente = (bool) ($it['bloquea_recurrente'] ?? ($svc->bloquea_recurrente ?? false));

                $cobroPrimerMes = $it['cobro_primer_mes'] ?? 'prorrata';
                if ($tipo !== 'recurrente') {
                    $cobroPrimerMes = 'prorrata';
                }
                $noCobrarPrimerPeriodo = ($cobroPrimerMes === 'gratis');

                return [
                    'servicio_id' => $svc->id,
                    'nombre'      => $nombreMostrado,
                    'tipo'        => $tipo,

                    'es_editable'      => $esEditable,

                    // ✅ fuente de verdad
                    'servicio_requiere_proyecto' => $servicioRequiereProyecto,
                    // ⚠️ legacy
                    'requiere_proyecto' => $requiereProyectoEditable,

                    'bloquea_recurrente' => $bloqueaRecurrente,

                    'cobro_primer_mes' => $cobroPrimerMes,
                    'no_cobrar_primer_periodo' => $noCobrarPrimerPeriodo,

                    'precio_base' => round($precioParaContrato, 2),
                    'unidades'    => $cantidad,
                    'total_linea' => round($subtotalFinal, 2),

                    'es_tarifa_principal' => $tipo === 'recurrente',

                    'es_alta_autonomo' => (
                        str_contains($nombreDetector, 'alta') &&
                        (str_contains($nombreDetector, 'autonom') || str_contains($nombreDetector, 'autónom'))
                    ),
                    'es_creacion_sociedad' =>
                        str_contains($nombreDetector, 'sociedad') ||
                        str_contains($nombreDetector, 'sl') ||
                        str_contains($nombreDetector, 'constitución'),
                    'es_capitalizacion' => str_contains($nombreDetector, 'capitaliz'),

                    'precio_base_original' => round($precioBaseOriginal, 2),
                    'precio_final_unit'    => round($precioFinalUnit, 2),
                    'subtotal_base'        => round((float) ($it['subtotal_base'] ?? ($precioBaseOriginal * $cantidad)), 2),
                    'subtotal_final'       => round($subtotalFinal, 2),

                    'descuento' => [
                        'aplicar'  => (bool) ($it['aplicar_descuento'] ?? false),
                        'tipo'     => $it['descuento_tipo'] ?? null,
                        'valor'    => $it['descuento_valor'] ?? null,
                        'meses'    => $it['descuento_duracion_meses'] ?? null,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'servicio_id' => null,
            'tipo' => 'unico',

            'es_editable' => false,

            // ✅ fuente de verdad
            'servicio_requiere_proyecto' => false,
            // ⚠️ legacy
            'requiere_proyecto' => false,

            'bloquea_recurrente' => false,

            'nombre_personalizado' => null,

            'precio_base' => 0,
            'cantidad' => 1,

            'cobro_primer_mes' => 'prorrata',

            'aplicar_descuento' => false,
            'descuento_tipo' => null,
            'descuento_valor' => null,
            'descuento_duracion_meses' => null,

            'subtotal_base' => 0,
            'precio_final_unit' => 0,
            'subtotal_final' => 0,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updated($name, $value): void
    {
        if (preg_match('/^items\.(\d+)\.servicio_id$/', $name, $m)) {
            $index = (int) $m[1];
            $this->syncServicio($index, $value ? (int) $value : null);
            return;
        }

        if (preg_match('/^items\.(\d+)\./', $name, $m)) {
            $index = (int) $m[1];

            if (str_ends_with($name, '.aplicar_descuento')) {
                $aplicar = (bool) ($this->items[$index]['aplicar_descuento'] ?? false);

                if (! $aplicar) {
                    $this->items[$index]['descuento_tipo'] = null;
                    $this->items[$index]['descuento_valor'] = null;
                    $this->items[$index]['descuento_duracion_meses'] = null;
                }
            }

            if (str_ends_with($name, '.descuento_tipo')) {
                $this->items[$index]['descuento_valor'] = null;

                if (($this->items[$index]['tipo'] ?? 'unico') !== 'recurrente') {
                    $this->items[$index]['descuento_duracion_meses'] = null;
                }
            }

            if (str_ends_with($name, '.descuento_valor')) {
                $raw = $this->items[$index]['descuento_valor'] ?? null;

                if ($raw === '' || $raw === null) {
                    $this->items[$index]['descuento_valor'] = null;
                } else {
                    $this->items[$index]['descuento_valor'] = (string) $raw;
                }
            }

            $this->enforceDiscountRules($index);
            $this->recalculateItem($index);
        }
    }

    private function syncServicio(int $index, ?int $servicioId): void
    {
        if (! $servicioId) {
            $this->items[$index]['servicio_id'] = null;
            $this->items[$index]['precio_base'] = 0;
            $this->items[$index]['tipo'] = 'unico';
            $this->items[$index]['es_editable'] = false;

            $this->items[$index]['servicio_requiere_proyecto'] = false;
            $this->items[$index]['requiere_proyecto'] = false;

            $this->items[$index]['nombre_personalizado'] = null;
            $this->items[$index]['bloquea_recurrente'] = false;

            $this->items[$index]['cobro_primer_mes'] = 'prorrata';

            $this->items[$index]['aplicar_descuento'] = false;
            $this->items[$index]['descuento_tipo'] = null;
            $this->items[$index]['descuento_valor'] = null;
            $this->items[$index]['descuento_duracion_meses'] = null;

            $this->recalculateItem($index);
            return;
        }

        /** @var Servicio|null $s */
        $s = $this->servicios->firstWhere('id', $servicioId);

        if (! $s) {
            $this->recalculateItem($index);
            return;
        }

        $tipo = $s->tipo instanceof \BackedEnum
            ? $s->tipo->value
            : ($s->tipo instanceof \UnitEnum ? $s->tipo->name : (string) $s->tipo);

        $esEditable = (bool) ($s->es_editable ?? false);

        // ✅ PROYECTO: SIEMPRE viene del Servicio
        $requiereProyectoAuto = (bool) ($s->requiere_proyecto_activacion ?? false);

        $this->items[$index]['servicio_id'] = $s->id;
        $this->items[$index]['tipo'] = (string) $tipo;
        $this->items[$index]['es_editable'] = $esEditable;

        $this->items[$index]['servicio_requiere_proyecto'] = $requiereProyectoAuto;
        $this->items[$index]['requiere_proyecto'] = false; // legacy OFF

        $this->items[$index]['bloquea_recurrente'] = (bool) ($s->bloquea_recurrente ?? false);

        $this->items[$index]['precio_base'] = (float) ($s->precio_base ?? 0);

        if ($esEditable) {
            $actual = $this->items[$index]['nombre_personalizado'] ?? null;
            if (! filled($actual)) {
                $this->items[$index]['nombre_personalizado'] = $s->nombre;
            }
        } else {
            $this->items[$index]['nombre_personalizado'] = null;
        }

        if (($this->items[$index]['tipo'] ?? 'unico') !== 'recurrente') {
            $this->items[$index]['cobro_primer_mes'] = 'prorrata';
        }

        $this->enforceDiscountRules($index);
        $this->recalculateItem($index);
    }

    private function enforceDiscountRules(int $index): void
    {
        $tipo = (string) ($this->items[$index]['tipo'] ?? 'unico');

        if ($tipo !== 'recurrente') {
            $this->items[$index]['descuento_duracion_meses'] = null;
            $this->items[$index]['cobro_primer_mes'] = 'prorrata';
            return;
        }

        $aplicar = (bool) ($this->items[$index]['aplicar_descuento'] ?? false);

        if ($aplicar) {
            $this->items[$index]['descuento_tipo'] = 'porcentaje';

            $meses = (int) ($this->items[$index]['descuento_duracion_meses'] ?? 0);
            if ($meses < 1) {
                $this->items[$index]['descuento_duracion_meses'] = 1;
            }
        } else {
            $this->items[$index]['descuento_duracion_meses'] = null;
        }
    }

    private function parseNumber(?string $value): float
    {
        if ($value === null) return 0.0;

        $v = trim($value);
        if ($v === '') return 0.0;

        $v = str_replace([' ', '€'], '', $v);
        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function recalculateItem(int $index): void
    {
        $cantidad = (float) ($this->items[$index]['cantidad'] ?? 1);
        $cantidad = $cantidad <= 0 ? 1 : $cantidad;

        $precioBase = (float) ($this->items[$index]['precio_base'] ?? 0);
        $subtotalBase = round($cantidad * $precioBase, 2);

        $aplicarDto = (bool) ($this->items[$index]['aplicar_descuento'] ?? false);
        $dtoTipo = $aplicarDto ? ($this->items[$index]['descuento_tipo'] ?? null) : null;

        $dtoValorRaw = $aplicarDto ? ($this->items[$index]['descuento_valor'] ?? null) : null;
        $dtoValor = $this->parseNumber($dtoValorRaw);

        $subtotalFinal = $subtotalBase;

        if ($dtoTipo && $dtoValorRaw !== null && $dtoValorRaw !== '') {
            switch ($dtoTipo) {
                case 'porcentaje':
                    $subtotalFinal = round($subtotalBase - ($subtotalBase * ($dtoValor / 100)), 2);
                    break;
                case 'fijo':
                    $subtotalFinal = round($subtotalBase - $dtoValor, 2);
                    break;
                case 'precio_final':
                    $subtotalFinal = round($dtoValor, 2);
                    break;
            }
        }

        $subtotalFinal = max(0, $subtotalFinal);

        $precioFinalUnit = $cantidad > 0 ? round($subtotalFinal / $cantidad, 2) : 0;

        $this->items[$index]['subtotal_base'] = $subtotalBase;
        $this->items[$index]['subtotal_final'] = $subtotalFinal;
        $this->items[$index]['precio_final_unit'] = $precioFinalUnit;
    }

    public function getTieneProyectoProperty(): bool
    {
        foreach ($this->items as $it) {
            if (! empty($it['servicio_requiere_proyecto'])) {
                return true;
            }
        }
        return false;
    }

    public function getTieneBloqueoRecurrenteProperty(): bool
    {
        foreach ($this->items as $it) {
            if (! empty($it['bloquea_recurrente'])) {
                return true;
            }
        }

        return false;
    }

public function getTotalesProperty(): array
{
    $unico = 0.0;
    $recurrente = 0.0;
    $prorrata = 0.0;

    $unicosNombres = [];
    $recurrentesNombres = [];

    $diasMes = now()->daysInMonth ?: 30;
    $diasRestantes = $diasMes - now()->day + 1;

    // ✅ 1) Flags de proyecto
    // - hay_proyecto: cualquier proyecto (editable o no) -> para UI/modal
    // - tiene_proyecto: SOLO proyectos que deben BLOQUEAR prorrata (no editables con servicio_requiere_proyecto)
    $hayProyecto = (bool) $this->tieneProyecto;

    $tieneProyectoBloqueante = false;
    foreach ($this->items as $it) {
        $esEditable = (bool) ($it['es_editable'] ?? false);

        // Solo NO editables (proyecto "real" de onboarding) bloquean prorrata
        if (! $esEditable && ! empty($it['servicio_requiere_proyecto'])) {
            $tieneProyectoBloqueante = true;
            break;
        }
    }

    // ✅ 2) Bloqueo recurrente (checkbox o servicio)
    $bloqueaRec = (bool) $this->tieneBloqueoRecurrente;

    // ✅ 3) La prorrata se difiere SOLO si:
    // - hay bloqueo recurrente, o
    // - hay proyecto bloqueante (NO editable)
    $diferirProrrata = $bloqueaRec || $tieneProyectoBloqueante;

    foreach ($this->items as $it) {
        $subtotalFinal = (float) ($it['subtotal_final'] ?? 0);
        $tipo = (string) ($it['tipo'] ?? 'unico');

        $servicioNombre = null;
        if (! empty($it['servicio_id'])) {
            $svc = $this->servicios->firstWhere('id', (int) $it['servicio_id']);
            $servicioNombre = $svc?->nombre;
        }

        $nombreMostrado = $servicioNombre;
        if (! empty($it['es_editable'])) {
            $nombreMostrado = $it['nombre_personalizado'] ?: $servicioNombre;
        }

        if ($tipo === 'recurrente') {
            $recurrente += $subtotalFinal;
            if ($nombreMostrado) $recurrentesNombres[] = $nombreMostrado;

            // ✅ PRORRATA: solo si NO hay diferimiento
            if (! $diferirProrrata) {
                $cobro = $it['cobro_primer_mes'] ?? 'prorrata';

                if ($cobro === 'completo') {
                    $prorrata += $subtotalFinal;
                } elseif ($cobro === 'gratis') {
                    $prorrata += 0;
                } else {
                    $parcial = ($subtotalFinal / $diasMes) * $diasRestantes;
                    $prorrata += $parcial;
                }
            }
        } else {
            $unico += $subtotalFinal;
            if ($nombreMostrado) $unicosNombres[] = $nombreMostrado;
        }
    }

    $totalHoySinIva = round($unico + $prorrata, 2);
    $factorIva = 1.21;

    return [
        'unico' => round($unico, 2),
        'recurrente' => round($recurrente, 2),

        'prorrata' => round($prorrata, 2),
        'dias_restantes' => $diasRestantes,
        'total_hoy_sin_iva' => $totalHoySinIva,

        'unico_con_iva' => round($unico * $factorIva, 2),
        'recurrente_con_iva' => round($recurrente * $factorIva, 2),
        'prorrata_con_iva' => round($prorrata * $factorIva, 2),
        'total_hoy_con_iva' => round($totalHoySinIva * $factorIva, 2),

        'lista_unicos' => $unicosNombres,
        'lista_recurrentes' => $recurrentesNombres,

        // ✅ Mantén compatibilidad con tu KPI actual:
        // KPI mira "tiene_proyecto" para desactivar prorrata -> ahora será SOLO bloqueante
        'tiene_proyecto' => $tieneProyectoBloqueante,

        // ✅ Nuevo: para UIs/modal (tu “hay proyecto -> prorrata activa”)
        'hay_proyecto' => $hayProyecto,

        'tiene_bloqueo_recurrente' => $bloqueaRec,
    ];
}



    public function confirmarDesdeResumen(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes generar una nueva propuesta.')
                ->warning()
                ->send();
            return;
        }

        $this->emailConfirm = null;

        $this->dispatch('close-modal', id: 'confirmar-propuesta');
        $this->dispatch('open-modal', id: 'confirmar-email');
    }

    public function enviarPropuestaConfirmada(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes reenviar ni generar enlaces.')
                ->warning()
                ->send();
            return;
        }

        $emailDestino = (string) ($this->emailDestino ?? '');

        $this->validate([
            'emailConfirm' => [
                'required',
                'email',
                function ($attribute, $value, $fail) use ($emailDestino) {
                    $a = mb_strtolower(trim((string) $value));
                    $b = mb_strtolower(trim($emailDestino));

                    if ($b === '' || $a !== $b) {
                        $fail('El email no coincide con el destinatario.');
                    }
                },
            ],
        ]);

        $this->dispatch('close-modal', id: 'confirmar-email');

        $record = $this->lead->fresh();

        $itemsServicios = $this->buildItemsServiciosBlueprint();

        if (empty($itemsServicios)) {
            Notification::make()
                ->title('Error')
                ->body('Debes añadir al menos un servicio.')
                ->danger()
                ->send();
            return;
        }

        $link = LeadConversionLink::active()
            ->where('lead_id', $record->id)
            ->first();

        if (! $link) {
            $link = LeadConversionLink::createForLead($record, 'automatic_multi');
        }

        $meta = $link->meta ?? [];
        $meta['form_type'] = 'automatic_multi';
        $meta['sale_blueprint'] = [
            'modo'      => 'automatico',
            'servicios' => $itemsServicios,
        ];

        $meta['form_data'] = array_merge(($meta['form_data'] ?? []), [
            'email'           => $emailDestino,
            'tipo_cliente_id' => $this->tipoClienteId,
        ]);

        $link->meta = $meta;
        $link->save();

        $record->estado = LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA;
        $record->fecha_cierre = null;
        $record->save();

        try {
            Mail::to($emailDestino)->send(new LeadConversionLinkMail($record, $link));

            LeadAutoEmailLog::create([
                'lead_id'              => $record->id,
                'estado'               => $record->estado->value,
                'intento'              => 1,
                'template_identifier'  => 'conversion_link_propuesta',
                'subject'              => 'Completa tu alta con AsesorFy',
                'body_preview'         => 'Enlace al formulario de conversión...',
                'scheduled_at'         => now(),
                'sent_at'              => now(),
                'status'               => 'sent',
                'triggered_by_user_id' => auth()->id(),
                'trigger_source'       => 'manual_action_filament_gestionar_conversion',
            ]);

            $record->comentarios()->create([
                'user_id'   => 9999,
                'contenido' => "🚀 🔗 Propuesta/enlace para firma de contrato enviado correctamente a {$emailDestino}.",
            ]);

            Notification::make()
                ->title('Propuesta enviada')
                ->body("Email enviado a {$emailDestino}.")
                ->success()
                ->send();

            $meta = $link->meta ?? [];
            $meta['last_sent_to'] = $emailDestino;
            $meta['last_sent_at'] = now()->toDateTimeString();
            $link->meta = $meta;
            $link->save();

            $this->lead = $record->fresh();
            $this->loadConversionInfo();

            $serviciosBlueprint = data_get($this->conversionInfo, 'blueprint.servicios', []);
            if (is_array($serviciosBlueprint) && ! empty($serviciosBlueprint)) {
                $this->items = $this->mapBlueprintServiciosToItems($serviciosBlueprint);
            }

            $this->dispatch('$refresh');

        } catch (Exception $e) {
            Notification::make()
                ->title('Error envío email')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function abrirConfirmarPropuesta(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes generar una nueva propuesta.')
                ->warning()
                ->send();
            return;
        }

        $hayServicios = collect($this->items)
            ->pluck('servicio_id')
            ->filter()
            ->isNotEmpty();

        if (! $hayServicios) {
            Notification::make()
                ->title('Añade al menos un servicio antes de generar la propuesta.')
                ->warning()
                ->send();

            return;
        }

        $this->dispatch('open-modal', id: 'confirmar-propuesta');
    }

    public function abrirConfirmarReenviar(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes reenviar emails.')
                ->warning()
                ->send();
            return;
        }

        $this->dispatch('open-modal', id: 'confirmar-reenviar-email');
    }

    public function abrirConfirmarReiniciarToken(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes reiniciar el token.')
                ->warning()
                ->send();
            return;
        }

        $this->dispatch('open-modal', id: 'confirmar-reiniciar-token');
    }

    public function abrirConfirmarCancelarConversion(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes cancelar la conversión.')
                ->warning()
                ->send();
            return;
        }

        $this->cancelReason = null;
        $this->dispatch('open-modal', id: 'confirmar-cancelar-conversion');
    }

    public function reenviarPropuestaVisual(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes reenviar emails.')
                ->warning()
                ->send();
            return;
        }

        $this->dispatch('close-modal', id: 'confirmar-reenviar-email');

        $record = $this->lead->fresh();

        $emailDestino = trim((string) ($this->emailDestino ?? ''));
        if ($emailDestino === '' || ! filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->title('Email no válido')
                ->body('No hay un email destino válido para reenviar.')
                ->danger()
                ->send();
            return;
        }

        $link = LeadConversionLink::active()
            ->where('lead_id', $record->id)
            ->latest('id')
            ->first();

        if (! $link || $link->isExpired()) {
            $this->reiniciarTokenVisual();
            return;
        }

        try {
            Mail::to($emailDestino)->send(new LeadConversionLinkMail($record, $link));

            LeadAutoEmailLog::create([
                'lead_id'              => $record->id,
                'estado'               => $record->estado?->value ?? 'convertido',
                'intento'              => 1,
                'template_identifier'  => 'conversion_link_reenvio',
                'subject'              => 'Completa tu alta con AsesorFy',
                'body_preview'         => 'Reenvío del enlace de conversión (mismo token).',
                'scheduled_at'         => now(),
                'sent_at'              => now(),
                'status'               => 'sent',
                'triggered_by_user_id' => auth()->id(),
                'trigger_source'       => 'manual_action_filament_gestionar_conversion',
            ]);

            $record->comentarios()->create([
                'user_id'   => 9999,
                'contenido' => "📩 🔁 Reenvío de enlace de conversión a {$emailDestino}.",
            ]);

            $meta = $link->meta ?? [];
            $meta['last_sent_to'] = $emailDestino;
            $meta['last_sent_at'] = now()->toDateTimeString();
            $link->meta = $meta;
            $link->save();

            Notification::make()
                ->title('Email reenviado')
                ->body("Se ha reenviado el enlace a {$emailDestino}.")
                ->success()
                ->send();

            $this->lead = $record->fresh();
            $this->loadConversionInfo();
            $this->dispatch('$refresh');

        } catch (Exception $e) {
            Notification::make()
                ->title('Error reenviando email')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function reiniciarTokenVisual(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes reiniciar el token.')
                ->warning()
                ->send();
            return;
        }

        $this->dispatch('close-modal', id: 'confirmar-reiniciar-token');

        $record = $this->lead->fresh();

        $emailDestino = trim((string) ($this->emailDestino ?? ''));
        if ($emailDestino === '' || ! filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->title('Email no válido')
                ->body('No hay un email destino válido para reiniciar y enviar.')
                ->danger()
                ->send();
            return;
        }

        $old = LeadConversionLink::active()
            ->where('lead_id', $record->id)
            ->latest('id')
            ->first();

        $blueprint = $old?->meta['sale_blueprint'] ?? data_get($this->conversionInfo, 'blueprint') ?? null;

        if (empty($blueprint) || empty($blueprint['servicios']) || ! is_array($blueprint['servicios'])) {
            $itemsServicios = $this->buildItemsServiciosBlueprint();
            if (empty($itemsServicios)) {
                Notification::make()
                    ->title('No hay servicios para generar enlace')
                    ->body('Añade al menos un servicio antes de reiniciar el token.')
                    ->warning()
                    ->send();
                return;
            }

            $blueprint = [
                'modo'      => 'automatico',
                'servicios' => $itemsServicios,
            ];
        }

        if ($old) {
            $metaOld = $old->meta ?? [];
            $metaOld['revoked_at'] = now()->toDateTimeString();
            $metaOld['revoked_by_user_id'] = auth()->id();

            $old->meta = $metaOld;
            $old->expires_at = now()->subSecond();
            $old->save();
        }

        $new = LeadConversionLink::createForLead($record, 'automatic_multi');

        $meta = $old?->meta ?? [];
        $meta['form_type'] = 'automatic_multi';
        $meta['sale_blueprint'] = $blueprint;

        $meta['form_data'] = array_merge(($meta['form_data'] ?? []), [
            'email'           => $emailDestino,
            'tipo_cliente_id' => $this->tipoClienteId,
        ]);

        $new->meta = $meta;
        $new->save();

        $record->estado = LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA;
        $record->fecha_cierre = null;
        $record->save();

        try {
            Mail::to($emailDestino)->send(new LeadConversionLinkMail($record, $new));

            LeadAutoEmailLog::create([
                'lead_id'              => $record->id,
                'estado'               => $record->estado->value,
                'intento'              => 1,
                'template_identifier'  => 'conversion_link_reinicio_token',
                'subject'              => 'Completa tu alta con AsesorFy',
                'body_preview'         => 'Nuevo enlace de conversión (token reiniciado).',
                'scheduled_at'         => now(),
                'sent_at'              => now(),
                'status'               => 'sent',
                'triggered_by_user_id' => auth()->id(),
                'trigger_source'       => 'manual_action_filament_gestionar_conversion',
            ]);

            $record->comentarios()->create([
                'user_id'   => 9999,
                'contenido' => "🧬 🔁 Token reiniciado y enlace enviado a {$emailDestino}.",
            ]);

            $metaNew = $new->meta ?? [];
            $metaNew['last_sent_to'] = $emailDestino;
            $metaNew['last_sent_at'] = now()->toDateTimeString();
            $new->meta = $metaNew;
            $new->save();

            Notification::make()
                ->title('Token reiniciado')
                ->body("Se ha generado un enlace nuevo y se ha enviado a {$emailDestino}.")
                ->success()
                ->send();

            $this->lead = $record->fresh();
            $this->loadConversionInfo();

            $serviciosBlueprint = data_get($this->conversionInfo, 'blueprint.servicios', []);
            if (is_array($serviciosBlueprint) && ! empty($serviciosBlueprint)) {
                $this->items = $this->mapBlueprintServiciosToItems($serviciosBlueprint);
            }

            $this->dispatch('$refresh');

        } catch (Exception $e) {
            Notification::make()
                ->title('Error enviando email')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelarConversionConfirmada(): void
    {
        if ($this->conversionBloqueada()) {
            Notification::make()
                ->title('Conversión bloqueada')
                ->body('Este lead ya está cerrado/firmado. No puedes cancelar la conversión.')
                ->warning()
                ->send();
            return;
        }

        $this->dispatch('close-modal', id: 'confirmar-cancelar-conversion');

        $record = $this->lead->fresh();

        $active = LeadConversionLink::active()
            ->where('lead_id', $record->id)
            ->latest('id')
            ->first();

        $blueprint = data_get($active?->meta, 'sale_blueprint');

        if ($active) {
            $meta = $active->meta ?? [];
            $meta['revoked_at'] = now()->toDateTimeString();
            $meta['revoked_by_user_id'] = auth()->id();
            $meta['revoked_reason'] = $this->cancelReason ? trim($this->cancelReason) : null;

            $active->meta = $meta;
            $active->expires_at = now()->subSecond();
            $active->save();
        }

        $record->estado = LeadEstadoEnum::CONVERTIDO_CORRECCION;
        $record->fecha_cierre = null;
        $record->save();

        $record->comentarios()->create([
            'user_id'   => 9999,
            'contenido' => '⛔ Conversión cancelada. ' . ($this->cancelReason ? ('Motivo: ' . trim($this->cancelReason)) : ''),
        ]);

        Notification::make()
            ->title('Conversión cancelada')
            ->body('Se ha revocado el enlace y el lead vuelve a modo edición.')
            ->success()
            ->send();

        if (is_array($blueprint) && ! empty($blueprint['servicios']) && is_array($blueprint['servicios'])) {
            $this->items = $this->mapBlueprintServiciosToItems($blueprint['servicios']);
        } else {
            if (empty($this->items)) {
                $this->addItem();
            }
        }

        $this->lead = $record->fresh();
        $this->loadConversionInfo();
        $this->dispatch('$refresh');
    }
}
