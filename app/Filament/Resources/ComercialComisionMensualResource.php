<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComercialComisionMensualResource\Pages;
use App\Models\ComercialHistorialObjetivo;
use App\Models\ComisionMensual;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class ComercialComisionMensualResource extends Resource
{
    protected static ?string $model = ComercialHistorialObjetivo::class;

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-currency-euro';
    protected static string|\UnitEnum|null   $navigationGroup = 'Comisiones';
    protected static ?string $navigationLabel  = 'Comisiones por Mes';
    protected static ?string $modelLabel       = 'Comisión Mensual';
    protected static ?string $pluralModelLabel = 'Comisiones por Mes';
    protected static ?int    $navigationSort   = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user->hasRole('comercial') && !$user->hasRole(['super_admin', 'coordinador'])) {
            $query->where('comercial_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        Forms\Components\Placeholder::make('comercial_nombre')
                            ->label('Comercial')
                            ->content(fn ($record) => $record?->comercial?->name ?? 'N/A'),

                        Forms\Components\TextInput::make('año')
                            ->disabled(),

                        Forms\Components\TextInput::make('mes')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => ucfirst(\Carbon\Carbon::create(null, $state, 1)->locale('es')->monthName)),

                        Forms\Components\Toggle::make('alcanzo_todos_minimos_obligatorios')
                            ->label('Alcanzó todos los mínimos')
                            ->disabled()
                            ->inline(false),
                    ])
                    ->columns(4),

                Section::make('Desglose por Regla')
                    ->schema([
                        Forms\Components\Placeholder::make('desglose')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || empty($record->datos_adicionales['desglose_reglas'])) {
                                    return 'No hay desglose disponible.';
                                }

                                return new \Illuminate\Support\HtmlString(
                                    view('filament.components.desglose-reglas-comision', [
                                        'reglas'       => $record->datos_adicionales['desglose_reglas'],
                                        'comercialId'  => $record->comercial_id,
                                    ])->render()
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Bonos Adicionales')
                    ->schema([
                        Forms\Components\Repeater::make('bonos_datos')
                            ->label('Bonos')
                            ->schema([
                                Forms\Components\TextInput::make('importe')
                                    ->label('Importe')
                                    ->numeric()
                                    ->required()
                                    ->prefix('€')
                                    ->live(),

                                Forms\Components\Textarea::make('descripcion')
                                    ->label('Descripción')
                                    ->required()
                                    ->maxLength(500)
                                    ->rows(2),

                                Forms\Components\Hidden::make('creado_por_id')
                                    ->default(fn () => Auth::id()),

                                Forms\Components\Hidden::make('creado_at')
                                    ->default(fn () => now()->toDateTimeString()),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Añadir bono')
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $totalBonos = collect($state ?? [])->sum('importe') ?? 0;
                                $set('total_bonos', $totalBonos);
                                $set('total_final', ($get('total_comisiones_calculado') ?? 0) + $totalBonos);
                            })
                            ->visible(fn ($record) => !$record || in_array($record->estado, ['borrador', 'aprobada']))
                            ->disabled(fn ($record) => $record && $record->estado === 'pagada'),

                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('total_comisiones_calculado')
                                    ->label('Total Comisiones')
                                    ->disabled()
                                    ->prefix('€')
                                    ->numeric(),

                                Forms\Components\TextInput::make('total_bonos')
                                    ->label('Total Bonos')
                                    ->disabled()
                                    ->prefix('€')
                                    ->numeric()
                                    ->live(),

                                Forms\Components\TextInput::make('total_final')
                                    ->label('TOTAL A PAGAR')
                                    ->disabled()
                                    ->prefix('€')
                                    ->numeric()
                                    ->live(),
                            ]),
                    ])
                    ->visible(fn () => Auth::user()->hasRole(['super_admin', 'coordinador'])),

                Section::make('Estado')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'aprobada' => 'Aprobada',
                                'pagada'   => 'Pagada',
                            ])
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('comercial.name')
                    ->label('Comercial')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $comercial = $record->comercial;

                        if (!$comercial) {
                            return $state;
                        }

                        // Verificar si ESE MES/AÑO estaba en periodo de prueba
                        if ($comercial->mesEstaEnPeriodoPrueba($record->año, $record->mes)) {
                            $mesesTotales  = $comercial->meses_prueba;
                            $fechaMes      = \Carbon\Carbon::create($record->año, $record->mes, 1)->startOfMonth();
                            $mesDelPeriodo = (int) $comercial->fecha_inicio_comercial->copy()->startOfMonth()->diffInMonths($fechaMes) + 1;

                            return new \Illuminate\Support\HtmlString(
                                $state .
                                ' <span style="background-color:#fbbf24;color:#78350f;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;margin-left:8px;">⏳ EN PRUEBA (' . $mesDelPeriodo . '/' . $mesesTotales . ')</span>'
                            );
                        }

                        return $state;
                    })
                    ->html()
                    ->visible(fn () => Auth::user()->hasRole(['super_admin', 'coordinador'])),

                Tables\Columns\TextColumn::make('contrato_estado')
                    ->label('Contrato')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (!$record->comercial) return 'N/A';
                        if (!$record->comercial->tieneContratoFirmado()) return 'Sin firmar';
                        if (!$record->comercial->puedeAprobarComisiones()) return 'Anexo pendiente';
                        return 'Vigente';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Sin firmar'     => 'danger',
                        'Anexo pendiente' => 'warning',
                        'Vigente'        => 'success',
                        default          => 'gray',
                    })
                    ->tooltip(function ($record) {
                        if (!$record->comercial || !$record->comercial->puedeAprobarComisiones()) {
                            return 'El comercial debe firmar el contrato antes de aprobar comisiones';
                        }
                        return 'Contrato firmado y vigente';
                    })
                    ->visible(fn () => Auth::user()->hasRole(['super_admin', 'coordinador'])),

                Tables\Columns\TextColumn::make('año')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mes')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucfirst(\Carbon\Carbon::create(null, $state, 1)->locale('es')->monthName)),

                Tables\Columns\TextColumn::make('total_comisiones_calculado')
                    ->label('Comisiones')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_bonos')
                    ->label('Bonos')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_final')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\IconColumn::make('alcanzo_todos_minimos_obligatorios')
                    ->label('Mínimos')
                    ->boolean(),

                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'borrador' => 'warning',
                        'aprobada' => 'success',
                        'pagada'   => 'primary',
                        default    => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('comercial_id')
                    ->label('Comercial')
                    ->relationship(
                        'comercial',
                        'name',
                        fn (Builder $query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'comercial'))
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn () => Auth::user()->hasRole(['super_admin', 'coordinador'])),

                Tables\Filters\SelectFilter::make('año')
                    ->options(function () {
                        $años = [];
                        for ($i = now()->year; $i >= now()->year - 3; $i--) {
                            $años[$i] = $i;
                        }
                        return $años;
                    }),

                Tables\Filters\SelectFilter::make('mes')
                    ->options([
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                    ]),

                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'aprobada' => 'Aprobada',
                        'pagada'   => 'Pagada',
                    ]),

                TernaryFilter::make('alcanzo_todos_minimos_obligatorios')
                    ->label('Alcanzó mínimos')
                    ->placeholder('Todos')
                    ->trueLabel('Sí alcanzó')
                    ->falseLabel('No alcanzó'),

                Filter::make('pendiente_accion')
                    ->label('Pendiente de acción')
                    ->form([
                        Forms\Components\Select::make('tipo')
                            ->options([
                                'aprobar' => 'Pendientes de aprobar',
                                'pagar'   => 'Pendientes de pagar',
                            ])
                            ->placeholder('Todos'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['tipo'] ?? null) {
                            'aprobar' => $query->where('estado', 'borrador'),
                            'pagar'   => $query->where('estado', 'aprobada'),
                            default   => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['tipo'] ?? null) {
                            'aprobar' => 'Pendientes de aprobar',
                            'pagar'   => 'Pendientes de pagar',
                            default   => null,
                        };
                    }),

                TernaryFilter::make('en_prueba')
                    ->label('Comercial en prueba')
                    ->placeholder('Todos')
                    ->trueLabel('En prueba')
                    ->falseLabel('Fuera de prueba')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('comercial', function ($q) {
                            $q->whereNotNull('fecha_inicio_comercial')
                              ->whereRaw('DATE_ADD(fecha_inicio_comercial, INTERVAL meses_prueba MONTH) > NOW()');
                        }),
                        false: fn (Builder $q) => $q->whereHas('comercial', function ($q) {
                            $q->where(function ($q) {
                                $q->whereNull('fecha_inicio_comercial')
                                  ->orWhereRaw('DATE_ADD(fecha_inicio_comercial, INTERVAL meses_prueba MONTH) <= NOW()');
                            });
                        }),
                        blank: fn (Builder $q) => $q,
                    ),

                TernaryFilter::make('contrato_firmado')
                    ->label('Contrato firmado')
                    ->placeholder('Todos')
                    ->trueLabel('Con contrato firmado')
                    ->falseLabel('Sin contrato firmado')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('comercial.contratosIncentivos',
                            fn ($q) => $q->where('tipo', 'base')->whereNotNull('fecha_firma')),
                        false: fn (Builder $q) => $q->whereDoesntHave('comercial.contratosIncentivos',
                            fn ($q) => $q->where('tipo', 'base')->whereNotNull('fecha_firma')),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
                Action::make('revisar_aprobar')
                    ->label('Revisar y Aprobar')
                    ->color('warning')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->visible(fn ($record) => Auth::user()->hasRole(['super_admin', 'coordinador']) && $record->estado === 'borrador')
                    ->modalHeading('Revisar Comisiones del Mes')
                    ->modalWidth('7xl')
                    ->modalContent(function ($record) {
                        $ventas = \App\Models\Venta::where('user_id', $record->comercial_id)
                            ->whereYear('fecha_venta', $record->año)
                            ->whereMonth('fecha_venta', $record->mes)
                            ->with(['items.servicio', 'cliente'])
                            ->orderBy('fecha_venta')
                            ->get();

                        return view('filament.modals.revisar-comisiones', [
                            'historial'      => $record,
                            'ventas'         => $ventas,
                            'desgloseReglas' => $record->datos_adicionales['desglose_reglas'] ?? [],
                        ]);
                    })
                    ->modalSubmitActionLabel('Aprobar y Enviar Informe')
                    ->form([
                        TextInput::make('bono_importe')
                            ->label('Importe del Bono')
                            ->default('0')
                            ->prefix('€')
                            ->placeholder('0.00')
                            ->rule('numeric')
                            ->rule('min:0')
                            ->inputMode('decimal'),

                        TextInput::make('bono_descripcion')
                            ->label('Descripción del Bono')
                            ->maxLength(255)
                            ->placeholder('Ej: Bono por superación de objetivos'),
                    ])
                    ->modalFooterActionsAlignment('right')
                    ->action(function ($record, array $data) {
                        if (!$record->comercial?->puedeAprobarComisiones()) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede aprobar')
                                ->body('El comercial debe firmar el contrato de incentivos actualizado.')
                                ->persistent()
                                ->send();
                            return;
                        }

                        $bonoImporte     = isset($data['bono_importe']) ? (float) $data['bono_importe'] : 0;
                        $bonoDescripcion = $data['bono_descripcion'] ?? null;
                        $totalFinal      = $record->total_comisiones_calculado + $bonoImporte;

                        DB::beginTransaction();
                        try {
                            $record->update([
                                'total_bonos' => $bonoImporte,
                                'total_final' => $totalFinal,
                                'estado'      => 'aprobada',
                            ]);

                            if ($bonoImporte > 0 && $bonoDescripcion) {
                                $datosAdicionales                      = $record->datos_adicionales ?? [];
                                $datosAdicionales['bono_descripcion']  = $bonoDescripcion;
                                $record->update(['datos_adicionales' => $datosAdicionales]);
                            }

                            ComisionMensual::where('comercial_id', $record->comercial_id)
                                ->where('año', $record->año)
                                ->where('mes', $record->mes)
                                ->update([
                                    'estado'          => 'aprobada',
                                    'aprobada_por_id' => Auth::id(),
                                    'aprobada_at'     => now(),
                                ]);

                            $servicio = new \App\Services\InformeComisionService();
                            $servicio->generarInforme($record);

                            Mail::to($record->comercial->email)
                                ->send(new \App\Mail\InformeComisionMensualMail($record));

                            DB::commit();

                            Notification::make()
                                ->success()
                                ->title('Comisiones aprobadas')
                                ->body('El informe ha sido generado y enviado al comercial.')
                                ->send();

                        } catch (\Exception $e) {
                            DB::rollBack();

                            Notification::make()
                                ->danger()
                                ->title('Error al aprobar')
                                ->body($e->getMessage())
                                ->send();

                            Log::error('Error aprobando comisión', [
                                'historial_id' => $record->id,
                                'error'        => $e->getMessage(),
                            ]);
                        }
                    }),

                Action::make('ver_informe')
                    ->label('Ver Informe')
                    ->color('success')
                    ->icon('heroicon-o-document-check')
                    ->visible(fn ($record) => $record->estado === 'aprobada' && $record->informe_pdf_path)
                    ->url(fn ($record) => route('descargar-informe-comision', ['id' => $record->id]))
                    ->openUrlInNewTab(),

                Action::make('ver_detalle_completo')
                    ->label('Ver Detalle')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Detalle de Comisiones del Mes')
                    ->modalWidth('7xl')
                    ->modalContent(function ($record) {
                        $ventas = \App\Models\Venta::where('user_id', $record->comercial_id)
                            ->whereYear('fecha_venta', $record->año)
                            ->whereMonth('fecha_venta', $record->mes)
                            ->with(['items.servicio', 'cliente'])
                            ->orderBy('fecha_venta')
                            ->get();

                        return view('filament.modals.revisar-comisiones', [
                            'historial'      => $record,
                            'ventas'         => $ventas,
                            'desgloseReglas' => $record->datos_adicionales['desglose_reglas'] ?? [],
                            'soloLectura'    => true,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                Action::make('marcar_pagada')
                    ->label('Marcar Pagada')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('pagada_referencia')
                            ->label('Referencia de Pago')
                            ->required()
                            ->placeholder('Ej: Nómina Abril 2026'),
                    ])
                    ->visible(fn ($record) => Auth::user()->hasRole(['super_admin', 'coordinador']) && $record->estado === 'aprobada')
                    ->action(function ($record, array $data) {
                        $record->update(['estado' => 'pagada']);

                        ComisionMensual::where('comercial_id', $record->comercial_id)
                            ->where('año', $record->año)
                            ->where('mes', $record->mes)
                            ->update([
                                'estado'            => 'pagada',
                                'pagada_at'         => now(),
                                'pagada_referencia' => $data['pagada_referencia'],
                            ]);

                        Notification::make()
                            ->success()
                            ->title('Comisión marcada como pagada')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('año', 'desc');
    }

    public static function enviarEmailAprobacion($comercial, $año, $mes, $mesNombre, $historial): void
    {
        $alcanzaTodos   = $historial->alcanzo_todos_minimos_obligatorios;
        $estaEnPrueba   = $comercial->mesEstaEnPeriodoPrueba($año, $mes);

        if ($alcanzaTodos) {
            $plantillaCodigo = 'comision_positivo';
        } elseif ($estaEnPrueba) {
            $plantillaCodigo = 'comision_no_minimo_prueba';
        } else {
            $plantillaCodigo = 'comision_no_minimo';
        }

        $plantilla = \App\Models\EmailPlantillaComercial::where('codigo', $plantillaCodigo)
            ->where('activa', true)
            ->first();

        if (!$plantilla) {
            Log::warning("Plantilla {$plantillaCodigo} no encontrada");
            return;
        }

        // Analizar situación del comercial
        $algunaAlcanzo              = false;
        $todasAlcanzaron            = true;
        $algunaObligatoriaNoAlcanzo = false;

        if (!empty($historial->datos_adicionales['desglose_reglas'])) {
            foreach ($historial->datos_adicionales['desglose_reglas'] as $regla) {
                if ($regla['alcanzo_minimo']) {
                    $algunaAlcanzo = true;
                } else {
                    $todasAlcanzaron = false;
                }

                $reglaModel = \App\Models\ComisionRegla::where('nombre', $regla['regla_nombre'])->first();
                if ($reglaModel) {
                    $esObligatoria = DB::table('comercial_reglas')
                        ->where('comercial_id', $comercial->id)
                        ->where('regla_id', $reglaModel->id)
                        ->where('es_obligatoria', true)
                        ->exists();

                    if ($esObligatoria && !$regla['alcanzo_minimo']) {
                        $algunaObligatoriaNoAlcanzo = true;
                    }
                }
            }
        }

        $variables = [
            'comercial_nombre'     => $comercial->name,
            'mes'                  => $mesNombre,
            'año'                  => $año,
            'total_comision'       => '€' . number_format($historial->total_comisiones_calculado, 2, ',', '.'),
            'total_bonos'          => '€' . number_format($historial->total_bonos, 2, ',', '.'),
            'total_final'          => '€' . number_format($historial->total_final, 2, ',', '.'),
            // Período de prueba (usadas en plantilla comision_no_minimo_prueba)
            'mes_actual_prueba'    => '',
            'meses_totales_prueba' => '',
            'mes_fin_prueba'       => '',
        ];

        if ($estaEnPrueba && $comercial->fecha_inicio_comercial) {
            $mesTotales     = $comercial->meses_prueba ?? 3;
            $finPrueba      = $comercial->fecha_inicio_comercial->copy()->addMonths($mesTotales)->subDay();
            $mesActualPrueba = (int) $comercial->fecha_inicio_comercial->copy()->startOfMonth()
                ->diffInMonths(\Carbon\Carbon::create($año, $mes, 1)->startOfMonth()) + 1;

            $variables['mes_actual_prueba']    = $mesActualPrueba;
            $variables['meses_totales_prueba'] = $mesTotales;
            $variables['mes_fin_prueba']        = $finPrueba->format('d/m/Y');
        }

        // Tabla de desglose de reglas
        if (!empty($historial->datos_adicionales['desglose_reglas'])) {
            $tabla  = '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:20px 0;font-family:Arial,sans-serif;">';
            $tabla .= '<thead><tr style="background-color:#0ea5e9;color:white;">';
            $tabla .= '<th style="text-align:left;">Regla</th><th style="text-align:right;">Facturación</th><th style="text-align:right;">Mínimo</th><th style="text-align:center;">Alcanzó</th><th style="text-align:right;">Comisión</th>';
            $tabla .= '</tr></thead><tbody>';

            foreach ($historial->datos_adicionales['desglose_reglas'] as $fila) {
                $alcanza      = $fila['alcanzo_minimo'] ?? false;
                $rowColor     = $alcanza ? '#d1fae5' : '#fee2e2';
                $reglaModel   = \App\Models\ComisionRegla::where('nombre', $fila['regla_nombre'] ?? '')->first();
                $esObligatoria = false;
                if ($reglaModel) {
                    $esObligatoria = DB::table('comercial_reglas')
                        ->where('comercial_id', $comercial->id)
                        ->where('regla_id', $reglaModel->id)
                        ->where('es_obligatoria', true)
                        ->exists();
                }

                $nombreRegla = '<strong>' . ($fila['regla_nombre'] ?? '—') . '</strong>';
                if ($esObligatoria) {
                    $nombreRegla .= ' <span style="background-color:#dc2626;color:white;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;margin-left:8px;">⚠️ OBLIGATORIA</span>';
                }

                $tabla .= '<tr style="background-color:' . $rowColor . ';">';
                $tabla .= '<td style="padding:8px;">' . $nombreRegla . '</td>';
                $tabla .= '<td style="text-align:right;padding:8px;">€' . number_format($fila['facturacion_neta'] ?? 0, 2, ',', '.') . '</td>';
                $tabla .= '<td style="text-align:right;padding:8px;">€' . number_format($fila['minimo_requerido'] ?? 0, 2, ',', '.') . '</td>';
                $tabla .= '<td style="text-align:center;padding:8px;font-size:18px;">' . ($alcanza ? '✅' : '❌') . '</td>';
                if ($algunaObligatoriaNoAlcanzo && $alcanza && !empty($fila['comision_teorica']) && $fila['comision_teorica'] > 0) {
                    $celdaComision = '<span style="text-decoration:line-through;color:#9ca3af;margin-right:8px;">€' . number_format($fila['comision_teorica'], 2, ',', '.') . '</span>'
                                   . '<span style="color:#dc2626;font-weight:bold;">€0,00</span>';
                } else {
                    $celdaComision = '€' . number_format($fila['comision'] ?? 0, 2, ',', '.');
                }
                $tabla .= '<td style="text-align:right;padding:8px;font-weight:bold;">' . $celdaComision . '</td>';
                $tabla .= '</tr>';
            }

            $tabla .= '</tbody></table>';

            if ($algunaObligatoriaNoAlcanzo) {
                $tabla .= '<p style="background-color:#fee2e2;padding:12px;border-left:4px solid #dc2626;margin:10px 0;font-size:14px;">';
                $tabla .= '<strong>⚠️ Importante:</strong> Una o más reglas marcadas como OBLIGATORIAS no alcanzaron el mínimo. ';
                $tabla .= 'Según la política de incentivos, esto anula las comisiones de <strong>TODAS</strong> las reglas para este mes.';
                $tabla .= '</p>';
            }

            $variables['desglose_reglas'] = $tabla;
        } else {
            $variables['desglose_reglas'] = '<p>No hay desglose disponible.</p>';
        }

        // Tabla de bonos si existen
        if ($historial->total_bonos > 0) {
            $comisionConBonos = ComisionMensual::where('comercial_id', $historial->comercial_id)
                ->where('año', $historial->año)
                ->where('mes', $historial->mes)
                ->whereNotNull('bonos')
                ->first();

            if ($comisionConBonos && !empty($comisionConBonos->bonos)) {
                $tablaBonos  = '<h3 style="margin-top:30px;">🎁 Bonos Adicionales</h3>';
                $tablaBonos .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:10px 0;font-family:Arial,sans-serif;">';
                $tablaBonos .= '<thead><tr style="background-color:#f59e0b;color:white;"><th style="text-align:left;">Descripción</th><th style="text-align:right;">Importe</th></tr></thead><tbody>';

                foreach ($comisionConBonos->bonos as $bono) {
                    $tablaBonos .= '<tr style="background-color:#fef3c7;">';
                    $tablaBonos .= '<td style="padding:8px;">' . ($bono['descripcion'] ?? '') . '</td>';
                    $tablaBonos .= '<td style="text-align:right;padding:8px;font-weight:bold;">€' . number_format($bono['importe'] ?? 0, 2, ',', '.') . '</td>';
                    $tablaBonos .= '</tr>';
                }

                $tablaBonos .= '</tbody></table>';
                $variables['desglose_reglas'] .= $tablaBonos;
            }
        }

        if (!$alcanzaTodos) {
            $variables['meses_consecutivos_sin_minimo'] = static::contarMesesConsecutivos($comercial->id, $año, $mes);
        }

        // Verificar condiciones de despido e integrar alerta en el email
        // (no se aplica durante el período de prueba)
        $alertaDespido = '';
        $config = \App\Models\ConfiguracionComisiones::first();

        if ($config && !$alcanzaTodos && !$estaEnPrueba) {
            $mesesConsecutivos  = static::contarMesesConsecutivos($comercial->id, $año, $mes);
            $mesesAlternos      = static::contarMesesAlternos($comercial->id, $año, $mes, $config->periodo_meses_alternos);
            $cumpleConsecutivos = $mesesConsecutivos >= $config->meses_consecutivos_despido;
            $cumpleAlternos     = $mesesAlternos >= $config->meses_alternos_despido;

            if ($cumpleConsecutivos || $cumpleAlternos) {
                $condicion = $cumpleConsecutivos
                    ? "{$mesesConsecutivos} meses consecutivos sin alcanzar mínimo"
                    : "{$mesesAlternos} meses alternos sin alcanzar mínimo en {$config->periodo_meses_alternos} meses";

                $alertaDespido  = '<div style="background-color:#fee2e2;border-left:4px solid #dc2626;padding:16px;margin:30px 0;">';
                $alertaDespido .= '<h3 style="color:#dc2626;margin-top:0;">⚠️ ALERTA IMPORTANTE</h3>';
                $alertaDespido .= '<p><strong>Has cumplido las siguientes condiciones:</strong></p>';
                $alertaDespido .= '<ul><li>' . $condicion . '</li></ul>';
                $alertaDespido .= '<p>Según la política de la empresa, esto puede resultar en una revisión de tu situación laboral.</p>';
                $alertaDespido .= '<p><strong>El departamento de RRHH se pondrá en contacto contigo próximamente.</strong></p>';
                $alertaDespido .= '</div>';

                // Email separado a RRHH
                if (!empty($config->emails_notificacion_despido)) {
                    $asuntoRRHH    = "ALERTA - Comercial {$comercial->name} cumple condiciones de despido";
                    $contenidoRRHH = "<p>El comercial <strong>{$comercial->name}</strong> ({$comercial->email}) ha cumplido las condiciones de despido:</p>"
                                   . "<ul><li>{$condicion}</li></ul>"
                                   . "<ul><li>Meses consecutivos sin mínimo: {$mesesConsecutivos}</li>"
                                   . "<li>Meses alternos sin mínimo: {$mesesAlternos} (en {$config->periodo_meses_alternos} meses)</li>"
                                   . "<li>Periodo: {$mesNombre} {$año}</li></ul>"
                                   . "<p>Se requiere acción del departamento de RRHH.</p>";

                    foreach ($config->emails_notificacion_despido as $emailRRHH) {
                        Mail::send([], [], fn ($m) => $m->to($emailRRHH)->subject($asuntoRRHH)->html($contenidoRRHH));
                    }

                    \App\Models\ComercialAlerta::create([
                        'comercial_id'     => $comercial->id,
                        'año'              => $año,
                        'mes'              => $mes,
                        'tipo'             => 'despido_automatico',
                        'email_enviado_at' => now(),
                        'plantilla_codigo' => 'despido_aviso',
                        'destinatarios'    => $config->emails_notificacion_despido,
                    ]);
                }
            }
        }

        $variables['alerta_despido'] = $alertaDespido;

        $asunto    = static::reemplazarVariables($plantilla->asunto, $variables);
        $contenido = static::reemplazarVariables($plantilla->contenido_html, $variables);

        Mail::send([], [], fn ($m) => $m->to($comercial->email)->subject($asunto)->html($contenido));

        \App\Models\ComercialAlerta::create([
            'comercial_id'     => $comercial->id,
            'año'              => $año,
            'mes'              => $mes,
            'tipo'             => $alcanzaTodos ? 'resultado_positivo' : 'no_alcanza_minimo',
            'importe_comision' => $historial->total_final,
            'email_enviado_at' => now(),
            'plantilla_codigo' => $plantillaCodigo,
            'destinatarios'    => [$comercial->email],
        ]);
    }

    public static function verificarCondicionesDespido($comercial, $año, $mes, $mesNombre): void
    {
        $config = \App\Models\ConfiguracionComisiones::first();

        if (!$config) {
            return;
        }

        $mesesConsecutivos  = static::contarMesesConsecutivos($comercial->id, $año, $mes);
        $mesesAlternos      = static::contarMesesAlternos($comercial->id, $año, $mes, $config->periodo_meses_alternos);
        $cumpleConsecutivos = $mesesConsecutivos >= $config->meses_consecutivos_despido;
        $cumpleAlternos     = $mesesAlternos >= $config->meses_alternos_despido;

        if (!$cumpleConsecutivos && !$cumpleAlternos) {
            return;
        }

        $condicion = $cumpleConsecutivos
            ? "{$mesesConsecutivos} meses consecutivos sin alcanzar mínimo"
            : "{$mesesAlternos} meses alternos sin alcanzar mínimo en {$config->periodo_meses_alternos} meses";

        static::enviarEmailDespido($comercial, $año, $mes, $mesNombre, $condicion, $mesesConsecutivos, $mesesAlternos, $config);
    }

    public static function contarMesesConsecutivos($comercialId, $año, $mes): int
    {
        $consecutivos = 0;
        $fechaActual  = \Carbon\Carbon::create($año, $mes, 1);
        $comercial    = \App\Models\User::find($comercialId);

        for ($i = 0; $i < 24; $i++) {
            // Si el mes estaba en período de prueba, no cuenta ni para ni en contra
            if ($comercial && $comercial->mesEstaEnPeriodoPrueba($fechaActual->year, $fechaActual->month)) {
                break;
            }

            $historial = ComercialHistorialObjetivo::where('comercial_id', $comercialId)
                ->where('año', $fechaActual->year)
                ->where('mes', $fechaActual->month)
                ->first();

            if (!$historial) {
                break; // Sin datos: no contar meses inexistentes
            }

            if (!$historial->alcanzo_todos_minimos_obligatorios) {
                $consecutivos++;
                $fechaActual->subMonth();
            } else {
                break;
            }
        }

        return $consecutivos;
    }

    public static function contarMesesAlternos($comercialId, $año, $mes, $periodoMeses): int
    {
        $fechaFin    = \Carbon\Carbon::create($año, $mes, 1);
        $fechaInicio = $fechaFin->copy()->subMonths($periodoMeses - 1);
        $comercial   = \App\Models\User::find($comercialId);
        $sinMinimo   = 0;

        $fechaActual = $fechaInicio->copy();
        while ($fechaActual->lte($fechaFin)) {
            // Saltar meses en período de prueba
            if ($comercial && $comercial->mesEstaEnPeriodoPrueba($fechaActual->year, $fechaActual->month)) {
                $fechaActual->addMonth();
                continue;
            }

            $historial = ComercialHistorialObjetivo::where('comercial_id', $comercialId)
                ->where('año', $fechaActual->year)
                ->where('mes', $fechaActual->month)
                ->first();

            if ($historial && !$historial->alcanzo_todos_minimos_obligatorios) {
                $sinMinimo++;
            }

            $fechaActual->addMonth();
        }

        return $sinMinimo;
    }

    public static function enviarEmailDespido($comercial, $año, $mes, $mesNombre, $condicion, $mesesConsecutivos, $mesesAlternos, $config): void
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

        $asunto    = static::reemplazarVariables($plantilla->asunto, $variables);
        $contenido = static::reemplazarVariables($plantilla->contenido_html, $variables);

        Mail::send([], [], fn ($m) => $m->to($comercial->email)->subject($asunto)->html($contenido));

        if (!empty($config->emails_notificacion_despido)) {
            $asuntoRRHH    = "ALERTA - Comercial {$comercial->name} cumple condiciones de despido";
            $contenidoRRHH = "<p>El comercial <strong>{$comercial->name}</strong> ha cumplido las condiciones de despido:</p>"
                           . "<ul><li>{$condicion}</li></ul>"
                           . "<p>Meses consecutivos: {$mesesConsecutivos} | Meses alternos: {$mesesAlternos}</p>";

            foreach ($config->emails_notificacion_despido as $emailRRHH) {
                Mail::send([], [], fn ($m) => $m->to($emailRRHH)->subject($asuntoRRHH)->html($contenidoRRHH));
            }
        }

        \App\Models\ComercialAlerta::create([
            'comercial_id'     => $comercial->id,
            'año'              => $año,
            'mes'              => $mes,
            'tipo'             => 'despido_automatico',
            'email_enviado_at' => now(),
            'plantilla_codigo' => 'despido_aviso',
            'destinatarios'    => array_merge([$comercial->email], $config->emails_notificacion_despido ?? []),
        ]);
    }

    public static function reemplazarVariables(string $texto, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $texto = str_replace('{{' . $key . '}}', $value, $texto);
        }

        return $texto;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComercialComisionMensuales::route('/'),
            'view'  => Pages\ViewComercialComisionMensual::route('/{record}'),
            'edit'  => Pages\EditComercialComisionMensual::route('/{record}/edit'),
        ];
    }
}
