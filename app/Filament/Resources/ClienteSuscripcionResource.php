<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Section;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\ListClienteSuscripcions;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\CreateClienteSuscripcion;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\EditClienteSuscripcion;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Filament\Resources\ClienteSuscripcionResource\Pages;
use App\Models\ClienteSuscripcion;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\ViewColumn;
use App\Services\StripeSubscriptionService;
use Filament\Infolists\Components\RepeatableEntry;





class ClienteSuscripcionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = ClienteSuscripcion::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }




     public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cliente_id')
                ->relationship('cliente', 'razon_social')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('servicio_id')
                ->relationship('servicio', 'nombre')
                ->preload()
                ->searchable()
                ->required(),

            Select::make('estado')
                ->options(collect(ClienteSuscripcionEstadoEnum::cases())
                    ->mapWithKeys(fn ($estado) => [$estado->value => $estado->name]))
                ->required(),

            DatePicker::make('fecha_inicio')->required(),
            DatePicker::make('fecha_fin'),

            TextInput::make('precio_acordado')->numeric()->prefix('€')->required(),
            TextInput::make('cantidad')->numeric()->default(1),
            TextInput::make('ciclo_facturacion'),

            DatePicker::make('proxima_fecha_facturacion'),

            TextInput::make('descuento_tipo'),
            TextInput::make('descuento_valor')->numeric(),
            TextInput::make('descuento_descripcion'),
            DatePicker::make('descuento_valido_hasta'),

            TextInput::make('stripe_subscription_id'),
            Textarea::make('observaciones'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
         ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->columns([
            TextColumn::make('cliente.razon_social')->searchable(),
             TextColumn::make('nombre_final') // Usamos el accesor del modelo
        ->label('Servicio Contratado')
        ->searchable(query: function (Builder $query, string $search): Builder {
            // Hacemos que la búsqueda funcione en ambos campos
            return $query
                ->where('nombre_personalizado', 'like', "%{$search}%")
                ->orWhereHas('servicio', fn ($q) => $q->where('nombre', 'like', "%{$search}%"));
        }),
            TextColumn::make('estado')
                ->badge()
                ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
                    ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                    ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                    ClienteSuscripcionEstadoEnum::EN_PRUEBA => 'info',
                    ClienteSuscripcionEstadoEnum::IMPAGADA => 'danger',
                    ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION => 'warning',
                    ClienteSuscripcionEstadoEnum::CANCELADA => 'gray',
                    ClienteSuscripcionEstadoEnum::FINALIZADA => 'gray',
                    ClienteSuscripcionEstadoEnum::REEMPLAZADA => 'gray',
                    ClienteSuscripcionEstadoEnum::PAUSADA => 'secondary',
                    default => 'gray',
                })
            ->formatStateUsing(fn(ClienteSuscripcionEstadoEnum $state) => $state->getLabel()),
                    TextColumn::make('fecha_inicio')->date('d/m/Y'),
                    TextColumn::make('fecha_fin')->date('d/m/Y'),
                    TextColumn::make('precio_acordado')->money('EUR'),
            // ▼▼▼ REEMPLAZA LA COLUMNA DEL DESCUENTO POR ESTA ▼▼▼
        ViewColumn::make('descuento')
            ->label('Dto.')
            ->view('filament.tables.columns.discount-icon-tooltip') // <-- Carga nuestro archivo Blade
        ->tooltip(function ($record): ?string {
            if (!$record->descuento_tipo) {
                return null;
            }
            
            $descuentoVigente = $record->descuento_valido_hasta && now()->lte($record->descuento_valido_hasta);
            $prefix = $descuentoVigente ? '[EN CURSO]' : '[APLICADO FINALIZADO]';
            
            $parts = [$prefix];

            // ▼▼▼ LÓGICA DE TIEMPO RESTANTE ACTUALIZADA ▼▼▼
            if ($descuentoVigente) {
                // Obtenemos la diferencia de meses. Usamos ceil() para redondear hacia arriba.
                // Ej: si quedan 1.2 meses, lo contará como 2.
                $mesesRestantes = ceil(now()->floatDiffInMonths($record->descuento_valido_hasta));
                
                // Lo convertimos a entero para asegurar
                $mesesRestantesEntero = (int) $mesesRestantes;
                
                if ($mesesRestantesEntero > 1) {
                    $parts[] = "Quedan: {$mesesRestantesEntero} meses";
                } elseif ($mesesRestantesEntero === 1) {
                    $parts[] = "Queda: Este es el último mes";
                }
            }
            
            $parts[] = '---';
            $parts[] = 'Tipo: ' . $record->descuento_tipo;
            $valor = number_format($record->descuento_valor, 2, ',', '.');
            $parts[] = 'Valor: ' . ($record->descuento_tipo === 'porcentaje' ? "{$valor}%" : "{$valor} €");
            
            if ($record->descuento_duracion_meses) {
                $parts[] = 'Duración Total: ' . $record->descuento_duracion_meses . ' meses';
            }
            
            if ($record->descuento_valido_hasta) {
                $parts[] = 'Finaliza el: ' . $record->descuento_valido_hasta->format('d/m/Y');
            }
            if ($record->descuento_descripcion) {
                $parts[] = 'Descripción: ' . $record->descuento_descripcion;
            }
            
            return implode("\n", $parts);
        }),

                    TextColumn::make('ciclo_facturacion'),
                    TextColumn::make('proxima_fecha_facturacion')->date('d/m/Y'),
                    TextColumn::make('created_at')
                        ->dateTime('d/m/Y H:i')
                        ->label('Creado'),
                    TextColumn::make('updated_at')
                        ->dateTime('d/m/Y H:i')
                        ->label('Creado'),
                ])
                ->filters([
                        // Filtro por estado usando el Enum directamente
                        SelectFilter::make('estado')
                            ->options(ClienteSuscripcionEstadoEnum::class), // Filament v3 lo convierte a opciones automáticamente

                        // Filtro para buscar por cliente
                        SelectFilter::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'razon_social')
                            ->searchable()
                            ->preload(),

                        // Filtro para buscar por servicio
                        SelectFilter::make('servicio_id')
                            ->label('Servicio')
                            ->relationship('servicio', 'nombre')
                            ->searchable()
                            ->preload(),
                        
                        // Filtro para saber si es tarifa principal
                        TernaryFilter::make('es_tarifa_principal')
                            ->label('Es Tarifa Principal'),

                    // ▼▼▼ EL NUEVO FILTRO PARA FACTURACIÓN ▼▼▼
                        Filter::make('listos_para_facturar')
                            ->label('Listos para Facturar (Recurrentes Activos)')
                            ->query(function (Builder $query): Builder {
                                return $query
                                    // 1. Solo estado ACTIVA
                                    ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
                                    // 2. Solo servicios de tipo RECURRENTE
                                    ->whereHas('servicio', function (Builder $q) {
                                        $q->where('tipo', ServicioTipoEnum::RECURRENTE);
                                    })
                                    // 3. Que ya hayan empezado
                                    ->where('fecha_inicio', '<=', now())
                                    // 4. Y que no hayan finalizado
                                    ->where(function (Builder $q) {
                                        $q->whereNull('fecha_fin')
                                        ->orWhere('fecha_fin', '>=', now());
                                    });
                            })
                            ->toggle(), // Es un simple interruptor de Sí/No
                
                        Filter::make('filtros_combinados')
                        ->label('Filtros Avanzados')
                        ->schema([
                            Grid::make(4) // <-- Cambiamos la rejilla a 4 columnas
                                ->schema([
                                    Select::make('year')
                                        ->label('Año')
                                        ->options(fn () => ClienteSuscripcion::query()->selectRaw('YEAR(fecha_inicio) as year')->whereNotNull('fecha_inicio')->distinct()->orderBy('year', 'desc')->pluck('year', 'year')->toArray()),
                                    
                                    Select::make('month')
                                        ->label('Mes')
                                        ->options([
                                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                                        ]),
                                    
                                    Select::make('estado')
                                        ->label('Estado')
                                        ->options(ClienteSuscripcionEstadoEnum::class),
                                    
                                    // ▼▼▼ EL NUEVO FILTRO DE TIPO DE SERVICIO ▼▼▼
                                    Select::make('tipo_servicio')
                                        ->label('Tipo de Servicio')
                                        ->options(ServicioTipoEnum::class),
                                ])
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return $query
                                ->when(
                                    $data['year'],
                                    fn (Builder $query, $year): Builder => $query->whereYear('fecha_inicio', $year)
                                )
                                ->when(
                                    $data['month'],
                                    fn (Builder $query, $month): Builder => $query->whereMonth('fecha_inicio', $month)
                                )
                                ->when(
                                    $data['estado'],
                                    fn (Builder $query, $estado): Builder => $query->where('estado', $estado)
                                )
                                // ▼▼▼ Lógica para el nuevo filtro ▼▼▼
                                ->when(
                                    $data['tipo_servicio'],
                                    fn (Builder $query, $tipo): Builder => $query->whereHas('servicio', function (Builder $q) use ($tipo) {
                                        $q->where('tipo', $tipo);
                                    })
                                );
                        })
                        ->columnSpan(2),
        
                    ], layout: FiltersLayout::AboveContent) // <-- Coloca los filtros arriba de la tabla
                ->recordActions([
                    ViewAction::make(),
                    EditAction::make(),
                ])
                ->toolbarActions([
                    DeleteBulkAction::make(),
                ]);
    }
public static function infolist(Schema $schema): Schema
{
    $stripeBase = function (): string {
        $secret = (string) config('services.stripe.secret', '');
        $isLive = str_starts_with($secret, 'sk_live_');
        return $isLive ? 'https://dashboard.stripe.com' : 'https://dashboard.stripe.com/test';
    };

    // Saca el snapshot desde la Page (ViewRecord)
    $getSnap = static function ($livewire): array {
        $snap = $livewire->stripeSnapshot ?? [];
        return is_array($snap) ? $snap : [];
    };

    // Normaliza “puede venir en euros (60.44) o en cents (6044)”
    $toCents = static function ($value): ?int {
        if ($value === null || $value === '') return null;

        if (is_string($value)) {
            $v = trim($value);
            if ($v === '') return null;

            $v = str_replace([' ', '€'], ['', ''], $v);
            $v = str_replace(',', '.', $v);

            if (! is_numeric($v)) return null;

            // si tiene decimales, asumimos euros
            if (str_contains($v, '.')) {
                return (int) round(((float) $v) * 100);
            }

            // si no tiene decimales, asumimos cents
            return (int) $v;
        }

        if (is_float($value)) {
            // float => euros
            return (int) round($value * 100);
        }

        if (is_int($value)) {
            // int => cents
            return $value;
        }

        if (is_numeric($value)) {
            $v = (string) $value;
            if (str_contains($v, '.')) {
                return (int) round(((float) $value) * 100);
            }
            return (int) $value;
        }

        return null;
    };

    $fmtMoney = static function ($value) use ($toCents): string {
        $cents = $toCents($value);
        if ($cents === null) return '—';
        $eur = $cents / 100;
        return number_format($eur, 2, ',', '.') . ' €';
    };

    $fmtTs = static function ($ts, bool $dateOnly = false): string {
        if (empty($ts)) return '—';
        try {
            $c = \Carbon\Carbon::createFromTimestamp((int) $ts)->timezone(config('app.timezone', 'Europe/Madrid'));
            return $dateOnly ? $c->format('d/m/Y') : $c->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '—';
        }
    };

    $hasStripe = static fn (ClienteSuscripcion $record): bool =>
        filled($record->stripe_subscription_id) && filled($record->cliente?->stripe_customer_id);

    $mkTable = static function (array $headers, array $rows): string {
        if (empty($rows)) return '—';

        $h = '| ' . implode(' | ', $headers) . " |\n";
        $h .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";

        $b = '';
        foreach ($rows as $r) {
            $b .= '| ' . implode(' | ', array_map(static fn ($x) => (string) $x, $r)) . " |\n";
        }

        return $h . $b;
    };

    // Badge “Stripe-like” para cupón/canjes
    $couponBadgeColor = static function (array $snap): string {
        $c = data_get($snap, 'discount.coupon');
        if (! is_array($c) || empty($c['id'] ?? null)) return 'gray';

        $valid = $c['valid'] ?? null;
        $times = $c['times_redeemed'] ?? null;
        $max   = $c['max_redemptions'] ?? null;

        if ($valid === false) return 'danger';

        if (is_numeric($max) && is_numeric($times)) {
            $max   = (int) $max;
            $times = (int) $times;

            if ($max > 0 && $times >= $max) return 'danger';        // agotado
            if ($max > 0 && $times >= ($max - 1)) return 'warning'; // al límite
        }

        return 'success';
    };

    return $schema->components([
        // =========================
        // TOP: detalles + contexto
        // =========================
        Grid::make(3)->schema([
            Section::make('Detalles de la suscripción')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextEntry::make('nombre_final')
                        ->label('Servicio contratado')
                        ->weight('bold')
                        ->size('lg')
                        ->columnSpanFull(),

                    TextEntry::make('estado')
                        ->label('Estado (Local)')
                        ->badge()
                        ->formatStateUsing(fn (ClienteSuscripcionEstadoEnum $state) => $state->getLabel())
                        ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
                            ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                            ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                            ClienteSuscripcionEstadoEnum::CANCELADA,
                            ClienteSuscripcionEstadoEnum::FINALIZADA => 'danger',
                            default => 'gray',
                        }),

                    TextEntry::make('servicio.tipo')
                        ->label('Tipo de servicio')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state)
                        ->color(fn ($state) => ($state instanceof \BackedEnum ? $state->value : (string) $state) === ServicioTipoEnum::RECURRENTE->value ? 'info' : 'success'),

                    TextEntry::make('precio_acordado')
                        ->label('Precio')
                        ->money('eur')
                        ->helperText(fn (ClienteSuscripcion $record) =>
                            ($record->servicio?->tipo === ServicioTipoEnum::RECURRENTE)
                                ? 'Precio por ciclo de facturación'
                                : null
                        ),

                    TextEntry::make('ciclo_facturacion')
                        ->label('Periodicidad')
                        ->badge()
                        ->color('gray')
                        ->visible(fn (ClienteSuscripcion $record) => $record->servicio?->tipo === ServicioTipoEnum::RECURRENTE),

                    TextEntry::make('fecha_inicio')->label('Fecha inicio (Local)')->date('d/m/Y'),
                    TextEntry::make('fecha_fin')->label('Fecha fin (Local)')->date('d/m/Y')->placeholder('Indefinido'),
                ]),

            Section::make('Contexto')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('cliente.razon_social')
                        ->label('Cliente')
                        ->url(fn (ClienteSuscripcion $record) => ClienteResource::getUrl('view', ['record' => $record->cliente_id]))
                        ->openUrlInNewTab()
                        ->icon('heroicon-m-user-circle'),

                    TextEntry::make('ventaOrigen.id')
                        ->label('Venta de origen')
                        ->url(fn (ClienteSuscripcion $record) => VentaResource::getUrl('view', ['record' => $record->venta_origen_id]))
                        ->openUrlInNewTab()
                        ->icon('heroicon-m-shopping-cart')
                        ->formatStateUsing(fn ($state) => $state ? "Venta #{$state}" : '—'),

                    TextEntry::make('cliente.stripe_customer_id')
                        ->label('Stripe Customer')
                        ->placeholder('—')
                        ->icon('heroicon-m-link')
                        ->url(fn (ClienteSuscripcion $record) =>
                            filled($record->cliente?->stripe_customer_id)
                                ? ($stripeBase() . '/customers/' . $record->cliente->stripe_customer_id)
                                : null
                        )
                        ->openUrlInNewTab(),

                    TextEntry::make('stripe_subscription_id')
                        ->label('Stripe Subscription')
                        ->placeholder('—')
                        ->icon('heroicon-m-link')
                        ->url(fn (ClienteSuscripcion $record) =>
                            filled($record->stripe_subscription_id)
                                ? ($stripeBase() . '/subscriptions/' . $record->stripe_subscription_id)
                                : null
                        )
                        ->openUrlInNewTab(),
                ]),
        ]),

        // =========================
        // Stripe (tiempo real)
        // =========================
        Section::make('Stripe (tiempo real)')
            ->visible(fn (ClienteSuscripcion $record) => $hasStripe($record))
            ->columns(3)
            ->schema([
                // --- Fila 1
                TextEntry::make('stripe_status')
                    ->label('Estado (Stripe)')
                    ->badge()
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => filled($livewire->stripeError)
                        ? 'ERROR'
                        : ((string) (data_get($getSnap($livewire), 'subscription.status') ?? '—'))
                    )
                    ->color(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => filled($livewire->stripeError)
                        ? 'danger'
                        : match ((string) (data_get($getSnap($livewire), 'subscription.status') ?? '')) {
                            'active' => 'success',
                            'trialing' => 'info',
                            'past_due', 'unpaid' => 'warning',
                            'canceled', 'incomplete', 'incomplete_expired' => 'danger',
                            default => 'gray',
                        }
                    )
                    ->helperText(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): ?string => filled($livewire->stripeError)
                        ? str($livewire->stripeError)->limit(180)->toString()
                        : null
                    ),

                TextEntry::make('stripe_collection_method')
                    ->label('Cobro (Stripe)')
                    ->badge()
                    ->color('gray')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => (string) (data_get($getSnap($livewire), 'subscription.collection_method') ?? '—')),

                TextEntry::make('stripe_payment_method')
                    ->label('Método de pago (Stripe)')
                    ->badge()
                    ->color('gray')
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap): string {
                        $pm = data_get($getSnap($livewire), 'payment_method');
                        if (! is_array($pm)) return '—';

                        if (($pm['type'] ?? null) === 'card') {
                            $brand = strtoupper((string) ($pm['brand'] ?? 'CARD'));
                            $last4 = (string) ($pm['last4'] ?? '—');
                            $expM  = (string) ($pm['exp_month'] ?? '—');
                            $expY  = (string) ($pm['exp_year'] ?? '—');
                            return "{$brand} •••• {$last4} (exp {$expM}/{$expY})";
                        }

                        if (($pm['type'] ?? null) === 'sepa_debit') {
                            $last4 = (string) ($pm['last4'] ?? '—');
                            return "SEPA •••• {$last4}";
                        }

                        return strtoupper((string) ($pm['type'] ?? 'unknown'));
                    }),

                // --- Fila 2
                TextEntry::make('stripe_amount')
                    ->label('Importe (Stripe)')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => ($v = data_get($getSnap($livewire), 'items.0.unit_amount')) === null
                        ? '—'
                        : $fmtMoney($v)
                    ),

                TextEntry::make('stripe_period_start')
                    ->label('Periodo inicio')
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $fmtTs): string {
                        $snap = $getSnap($livewire);
                        $ts = data_get($snap, 'subscription.current_period_start')
                            ?: data_get($snap, 'upcoming_invoice.lines.0.period_start');
                        return $fmtTs($ts);
                    }),

                TextEntry::make('stripe_period_end')
                    ->label('Periodo fin')
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $fmtTs): string {
                        $snap = $getSnap($livewire);
                        $ts = data_get($snap, 'subscription.current_period_end')
                            ?: data_get($snap, 'upcoming_invoice.lines.0.period_end');
                        return $fmtTs($ts);
                    }),

                // --- Fila 3
                TextEntry::make('stripe_trial_end')
                    ->label('En prueba hasta')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => $fmtTs(data_get($getSnap($livewire), 'subscription.trial_end'), true))
                    ->helperText('Fecha en horario local (Europe/Madrid).'),

                TextEntry::make('stripe_cancel_at_period_end')
                    ->label('Cancelación fin de periodo')
                    ->badge()
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => (bool) data_get($getSnap($livewire), 'subscription.cancel_at_period_end') ? 'Sí' : 'No')
                    ->color(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => (bool) data_get($getSnap($livewire), 'subscription.cancel_at_period_end') ? 'warning' : 'gray'),

                TextEntry::make('stripe_latest_invoice')
                    ->label('Última factura (Stripe)')
                    ->badge()
                    ->color('gray')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => (string) (data_get($getSnap($livewire), 'subscription.latest_invoice_id') ?? '—')),

                // --- Fila 4
                TextEntry::make('stripe_latest_invoice_amount')
                    ->label('Importe última factura')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => ($paid = data_get($getSnap($livewire), 'subscription.latest_invoice_amount_paid')) !== null
                        ? $fmtMoney($paid)
                        : (($due = data_get($getSnap($livewire), 'subscription.latest_invoice_amount_due')) !== null ? $fmtMoney($due) : '—')
                    ),

                // --- CUPÓN / PROMO
                TextEntry::make('stripe_coupon')
                    ->label('Cupón (Stripe)')
                    ->badge()
                    ->color(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $couponBadgeColor): string {
                        return $couponBadgeColor($getSnap($livewire));
                    })
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ): string => (string) (data_get($getSnap($livewire), 'discount.coupon.id') ?? '—'))
                    ->helperText(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $fmtMoney, $fmtTs): ?string {
                        $c = data_get($getSnap($livewire), 'discount.coupon');
                        if (! is_array($c) || empty($c['id'] ?? null)) return null;

                        $parts = [];

                        if (filled($c['name'] ?? null)) $parts[] = 'Nombre: ' . $c['name'];

                        if (($c['percent_off'] ?? null) !== null) {
                            $parts[] = 'Descuento: ' . number_format((float) $c['percent_off'], 2, ',', '.') . ' %';
                        } elseif (($c['amount_off'] ?? null) !== null) {
                            $parts[] = 'Descuento: ' . $fmtMoney($c['amount_off']);
                        }

                        if (filled($c['duration'] ?? null)) {
                            $dur = (string) $c['duration'];
                            if ($dur === 'repeating' && filled($c['duration_in_months'] ?? null)) {
                                $dur .= ' (' . $c['duration_in_months'] . ' meses)';
                            }
                            $parts[] = 'Duración: ' . $dur;
                        }

                        if (filled($c['redeem_by'] ?? null)) {
                            $parts[] = 'Canjeable hasta: ' . $fmtTs($c['redeem_by'], true);
                        }

                        $times = $c['times_redeemed'] ?? null;
                        $max   = $c['max_redemptions'] ?? null;
                        if ($times !== null || $max !== null) {
                            $t = ($times !== null) ? (string) $times : '0';
                            $m = ($max !== null) ? (string) $max : '∞';
                            $parts[] = "Canjes: {$t} / {$m}";
                        }

                        return implode(' · ', $parts);
                    }),

                TextEntry::make('stripe_promo_code')
                    ->label('Código promo (Stripe)')
                    ->badge()
                    ->color('gray')
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap): string {
                        $code = data_get($getSnap($livewire), 'discount.promotion_code.code');

                        if (filled($code)) {
                            return (string) $code;
                        }

                        // si hay cupón pero no promo code => cupón directo
                        $couponId = data_get($getSnap($livewire), 'discount.coupon.id');
                        if (filled($couponId)) {
                            return 'No aplica (cupón directo)';
                        }

                        return '—';
                    }),
            ]),

        // =========================
        // Próxima factura (Stripe)
        // =========================
        Section::make('Próxima factura (Stripe)')
            ->visible(fn (ClienteSuscripcion $record) => $hasStripe($record))
            ->columns(3)
            ->schema([
                TextEntry::make('stripe_upcoming_total')
                    ->label('Total')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) => $fmtMoney(data_get($getSnap($livewire), 'upcoming_invoice.total'))),

                TextEntry::make('stripe_upcoming_tax')
                    ->label('Impuestos')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) => $fmtMoney(data_get($getSnap($livewire), 'upcoming_invoice.tax'))),

                TextEntry::make('stripe_upcoming_amount_due')
                    ->label('Amount due')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) => $fmtMoney(data_get($getSnap($livewire), 'upcoming_invoice.amount_due'))),

                // ✅ Esto es lo que te confirma “se está aplicando el cupón/promo” en la próxima factura:
                // descuento ≈ (subtotal + tax) - total
                TextEntry::make('stripe_upcoming_discount_applied')
                    ->label('Descuento aplicado')
                    ->badge()
                    ->color('success')
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $fmtMoney): string {
                        $u = data_get($getSnap($livewire), 'upcoming_invoice');

                        if (! is_array($u) || empty($u)) return '—';

                        $subtotal = (int) (data_get($u, 'subtotal') ?? 0);
                        $tax      = (int) (data_get($u, 'tax') ?? 0);
                        $total    = (int) (data_get($u, 'total') ?? 0);

                        $discount = ($subtotal + $tax) - $total;

                        if ($discount <= 0) return '—';

                        return $fmtMoney($discount);
                    })
                    ->helperText(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap): ?string {
                        $couponId = data_get($getSnap($livewire), 'discount.coupon.id');
                        $promo    = data_get($getSnap($livewire), 'discount.promotion_code.code');

                        if (filled($promo)) return "Aplicado por código: {$promo}";
                        if (filled($couponId)) return "Aplicado por cupón directo: {$couponId}";
                        return null;
                    }),

                TextEntry::make('stripe_upcoming_next_attempt')
                    ->label('Siguiente intento de cobro')
                    ->state(fn (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) => $fmtTs(data_get($getSnap($livewire), 'upcoming_invoice.next_payment_attempt')))
                    ->columnSpanFull(),

                TextEntry::make('stripe_upcoming_lines')
                    ->label('Líneas (Stripe)')
                    ->markdown()
                    ->columnSpanFull()
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $mkTable, $fmtMoney, $fmtTs): string {
                        $lines = data_get($getSnap($livewire), 'upcoming_invoice.lines', []);
                        if (! is_array($lines) || empty($lines)) return '—';

                        $rows = [];
                        foreach (array_slice($lines, 0, 20) as $l) {
                            $desc = (string) ($l['description'] ?? '—');
                            $qty  = (string) ($l['quantity'] ?? '—');
                            $amt  = $fmtMoney($l['amount'] ?? null);

                            $ps = $l['period_start'] ?? null;
                            $pe = $l['period_end'] ?? null;
                            $period = ($ps && $pe) ? ($fmtTs($ps, true) . ' → ' . $fmtTs($pe, true)) : '—';

                            $rows[] = [$desc, $period, $qty, $amt];
                        }

                        return $mkTable(['Descripción', 'Periodo', 'Cant.', 'Importe'], $rows);
                    }),

                TextEntry::make('stripe_upcoming_debug')
                    ->label('Diagnóstico')
                    ->helperText('Si Stripe no puede calcular la próxima factura, aquí verás el motivo.')
                    ->badge()
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap): string {
                        $upcoming = data_get($getSnap($livewire), 'upcoming_invoice');
                        if (is_array($upcoming) && ! empty($upcoming)) return 'OK';

                        $err = (string) (data_get($getSnap($livewire), 'upcoming_invoice_error') ?? '');
                        return $err !== '' ? $err : 'Stripe no devuelve upcoming_invoice (aún).';
                    })
                    ->color(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap): string {
                        $upcoming = data_get($getSnap($livewire), 'upcoming_invoice');
                        return (is_array($upcoming) && ! empty($upcoming)) ? 'success' : 'warning';
                    })
                    ->columnSpanFull(),
            ]),

        // =========================
        // Facturas (Stripe)
        // =========================
        Section::make('Facturas (Stripe)')
            ->visible(fn (ClienteSuscripcion $record) => $hasStripe($record))
            ->schema([
                TextEntry::make('stripe_invoices_table')
                    ->label('Últimas facturas')
                    ->html()
                    ->state(function (
                        ClienteSuscripcion $record,
                        \App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion $livewire
                    ) use ($getSnap, $fmtMoney, $fmtTs, $stripeBase): string {
                        $snap = $getSnap($livewire);

                        $invoices = data_get($snap, 'invoices', []);
                        if (! is_array($invoices) || empty($invoices)) {
                            $invoices = data_get($snap, 'invoices.data', []);
                        }

                        if (! is_array($invoices) || empty($invoices)) {
                            return '<div style="color:#64748b;">—</div>';
                        }

                        $rowsHtml = '';

                        foreach (array_slice($invoices, 0, 10) as $inv) {
                            $id      = (string) ($inv['id'] ?? '');
                            $status  = (string) ($inv['status'] ?? '—');
                            $created = $fmtTs($inv['created'] ?? null, true);
                            $total   = $fmtMoney($inv['total'] ?? null);

                            // enlace: hosted_invoice_url (cliente) -> si no, dashboard
                            $url = (string) ($inv['hosted_invoice_url'] ?? '');
                            if ($url === '' && $id !== '') {
                                $url = $stripeBase() . '/invoices/' . $id;
                            }

                            $label = $id !== '' ? $id : '—';

                            $invoiceCell = $url !== ''
                                ? '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" style="font-weight:600; text-decoration:underline;">' . e($label) . '</a>'
                                : '<span>' . e($label) . '</span>';

                            // badge estilo “pill”
                            $st = strtolower(trim($status));
                            $statusUpper = strtoupper($st !== '' ? $st : '—');

                            $bg = '#e2e8f0'; // gray
                            $fg = '#0f172a';

                            if ($st === 'paid') {
                                $bg = '#dcfce7'; // green
                                $fg = '#166534';
                            } elseif ($st === 'open') {
                                $bg = '#ffedd5'; // orange
                                $fg = '#9a3412';
                            } elseif ($st === 'uncollectible' || $st === 'void') {
                                $bg = '#fee2e2'; // red
                                $fg = '#991b1b';
                            } elseif ($st === 'draft') {
                                $bg = '#e2e8f0';
                                $fg = '#334155';
                            }

                            $statusCell =
                                '<span style="
                                    display:inline-flex;
                                    align-items:center;
                                    padding:2px 8px;
                                    border-radius:9999px;
                                    font-size:12px;
                                    font-weight:700;
                                    background:' . $bg . ';
                                    color:' . $fg . ';
                                ">' . e($statusUpper) . '</span>';

                            $rowsHtml .= '
                                <tr>
                                    <td style="padding:8px 10px; border-top:1px solid #e2e8f0; white-space:nowrap;">' . e($created) . '</td>
                                    <td style="padding:8px 10px; border-top:1px solid #e2e8f0; white-space:nowrap;">' . $invoiceCell . '</td>
                                    <td style="padding:8px 10px; border-top:1px solid #e2e8f0; white-space:nowrap;">' . $statusCell . '</td>
                                    <td style="padding:8px 10px; border-top:1px solid #e2e8f0; white-space:nowrap; text-align:right; font-variant-numeric: tabular-nums;">' . e($total) . '</td>
                                </tr>
                            ';
                        }

                        return '
                            <div style="overflow:auto;">
                                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left; padding:8px 10px; border-bottom:1px solid #e2e8f0; color:#475569;">Fecha</th>
                                            <th style="text-align:left; padding:8px 10px; border-bottom:1px solid #e2e8f0; color:#475569;">Invoice</th>
                                            <th style="text-align:left; padding:8px 10px; border-bottom:1px solid #e2e8f0; color:#475569;">Estado</th>
                                            <th style="text-align:right; padding:8px 10px; border-bottom:1px solid #e2e8f0; color:#475569;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ' . $rowsHtml . '
                                    </tbody>
                                </table>
                            </div>
                        ';
                    }),
            ]),
    ]);
}









    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClienteSuscripcions::route('/'),
            'create' => CreateClienteSuscripcion::route('/create'),
            'view' => ViewClienteSuscripcion::route('/{record}'), // <-- AÑADIR ESTA LÍNEA
            'edit' => EditClienteSuscripcion::route('/{record}/edit'),
        ];
    }
}
