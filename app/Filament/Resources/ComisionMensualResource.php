<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComisionMensualResource\Pages;
use App\Models\ComisionMensual;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ComisionMensualResource extends Resource
{
    protected static ?string $model = ComisionMensual::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup  = 'Comisiones';
    protected static ?string $navigationLabel  = 'Comisiones Mensuales';
    protected static ?string $modelLabel       = 'Comisión Mensual';
    protected static ?string $pluralModelLabel = 'Comisiones Mensuales';
    protected static ?int $navigationSort      = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos')
                    ->schema([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Comercial')
                            ->options(
                                User::whereHas('roles', fn ($q) => $q->where('name', 'comercial'))
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('regla_id')
                            ->label('Regla')
                            ->relationship('regla', 'nombre')
                            ->required(),

                        Forms\Components\TextInput::make('año')
                            ->required()
                            ->numeric()
                            ->default(now()->year),

                        Forms\Components\Select::make('mes')
                            ->required()
                            ->options([
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
                                4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
                                10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                            ])
                            ->default(now()->month),
                    ])
                    ->columns(4),

                Section::make('Cálculo')
                    ->schema([
                        Forms\Components\TextInput::make('facturacion_bruta')
                            ->label('Facturación Bruta (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('bajas_mes')
                            ->label('Bajas (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('facturacion_neta')
                            ->label('Facturación Neta (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('minimo_aplicable')
                            ->label('Mínimo Aplicable (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('base_comisionable')
                            ->label('Base Comisionable (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('porcentaje')
                            ->label('Porcentaje (%)')
                            ->numeric()
                            ->suffix('%')
                            ->readOnly(),

                        Forms\Components\TextInput::make('importe_comision_calculado')
                            ->label('Comisión Calculada (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('total_bonos')
                            ->label('Total Bonos (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),

                        Forms\Components\TextInput::make('importe_final')
                            ->label('Importe Final (€)')
                            ->numeric()
                            ->prefix('€')
                            ->readOnly(),
                    ])
                    ->columns(3),

                Section::make('Estado y Aprobación')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->options([
                                'borrador' => 'Borrador',
                                'aprobada' => 'Aprobada',
                                'pagada'   => 'Pagada',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('pagada_referencia')
                            ->label('Referencia de Pago')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('comercial.name')
                    ->label('Comercial')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('año')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mes')
                    ->formatStateUsing(fn ($state) => $meses[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('regla.nombre')
                    ->label('Regla')
                    ->limit(25),

                Tables\Columns\TextColumn::make('facturacion_neta')
                    ->label('Neta (€)')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('importe_final')
                    ->label('Comisión (€)')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\IconColumn::make('alcanzo_minimo')
                    ->label('Mínimo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'borrador' => 'gray',
                        'aprobada' => 'warning',
                        'pagada'   => 'success',
                        default    => 'gray',
                    }),
            ])
            ->defaultSort('año', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('comercial_id')
                    ->label('Comercial')
                    ->options(
                        User::whereHas('roles', fn ($q) => $q->where('name', 'comercial'))
                            ->pluck('name', 'id')
                    ),

                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'aprobada' => 'Aprobada',
                        'pagada'   => 'Pagada',
                    ]),

                Tables\Filters\SelectFilter::make('año')
                    ->options(function () {
                        $años = [];
                        for ($y = now()->year; $y >= now()->year - 3; $y--) {
                            $años[$y] = $y;
                        }
                        return $años;
                    }),
            ])
            ->actions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar comisión')
                    ->modalDescription('Se enviará email al comercial con el desglose completo y se verificarán condiciones de despido.')
                    ->visible(fn (ComisionMensual $record) => Auth::user()->hasRole(['super_admin', 'coordinador']) && $record->estado === 'borrador')
                    ->action(function (ComisionMensual $record) {
                        DB::beginTransaction();
                        try {
                            $record->update([
                                'estado'          => 'aprobada',
                                'aprobada_por_id' => Auth::id(),
                                'aprobada_at'     => now(),
                            ]);

                            $historial = \App\Models\ComercialHistorialObjetivo::where('comercial_id', $record->comercial_id)
                                ->where('año', $record->año)
                                ->where('mes', $record->mes)
                                ->first();

                            if ($historial) {
                                $historial->update(['estado' => 'aprobada']);
                            }

                            $comercial  = $record->comercial;
                            $año        = $record->año;
                            $mes        = $record->mes;
                            $mesNombre  = ucfirst(\Carbon\Carbon::create($año, $mes, 1)->locale('es')->monthName);

                            static::enviarEmailAprobacion($comercial, $año, $mes, $mesNombre, $historial);
                            static::verificarCondicionesDespido($comercial, $año, $mes, $mesNombre);

                            DB::commit();

                            Notification::make()
                                ->success()
                                ->title('Comisión aprobada')
                                ->body("Email enviado a {$comercial->email}")
                                ->send();

                        } catch (\Exception $e) {
                            DB::rollBack();

                            Notification::make()
                                ->danger()
                                ->title('Error al aprobar')
                                ->body($e->getMessage())
                                ->send();

                            Log::error('Error aprobando comisión', [
                                'comision_id' => $record->id,
                                'error'       => $e->getMessage(),
                            ]);
                        }
                    }),

                Action::make('marcar_pagada')
                    ->label('Marcar Pagada')
                    ->icon('heroicon-o-currency-euro')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ComisionMensual $record) => $record->estado === 'aprobada')
                    ->form([
                        Forms\Components\TextInput::make('referencia')
                            ->label('Referencia de pago')
                            ->required(),
                    ])
                    ->action(function (ComisionMensual $record, array $data) {
                        $record->update([
                            'estado'            => 'pagada',
                            'pagada_at'         => now(),
                            'pagada_referencia' => $data['referencia'],
                        ]);

                        Notification::make()->title('Comisión marcada como pagada')->success()->send();
                    }),

                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComisionesMensuales::route('/'),
            'view'  => Pages\ViewComisionMensual::route('/{record}'),
        ];
    }

    protected static function enviarEmailAprobacion($comercial, $año, $mes, $mesNombre, $historial): void
    {
        if (!$historial) {
            return;
        }

        $alcanzaTodos    = $historial->alcanzo_todos_minimos_obligatorios;
        $plantillaCodigo = $alcanzaTodos ? 'comision_positivo' : 'comision_no_minimo';

        $plantilla = \App\Models\EmailPlantillaComercial::where('codigo', $plantillaCodigo)
            ->where('activa', true)
            ->first();

        if (!$plantilla) {
            Log::warning("Plantilla {$plantillaCodigo} no encontrada");
            return;
        }

        $variables = [
            'comercial_nombre' => $comercial->name,
            'mes'              => $mesNombre,
            'año'              => $año,
            'total_comision'   => '€' . number_format($historial->total_comisiones_calculado, 2, ',', '.'),
            'total_bonos'      => '€' . number_format($historial->total_bonos, 2, ',', '.'),
            'total_final'      => '€' . number_format($historial->total_final, 2, ',', '.'),
        ];

        // Construir tabla de desglose si existe
        if (!empty($historial->datos_adicionales['desglose_reglas'])) {
            $tabla  = '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:20px 0;">';
            $tabla .= '<thead><tr style="background-color:#f3f4f6;">';
            $tabla .= '<th>Regla</th><th>Facturación</th><th>Mínimo</th><th>Alcanzó</th><th>Comisión</th>';
            $tabla .= '</tr></thead><tbody>';

            foreach ($historial->datos_adicionales['desglose_reglas'] as $fila) {
                $tabla .= '<tr>';
                $tabla .= '<td>' . ($fila['regla_nombre'] ?? '—') . '</td>';
                $tabla .= '<td>€' . number_format($fila['facturacion_neta'] ?? 0, 2, ',', '.') . '</td>';
                $tabla .= '<td>€' . number_format($fila['minimo_requerido'] ?? 0, 2, ',', '.') . '</td>';
                $tabla .= '<td>' . ($fila['alcanzo_minimo'] ? '✅ Sí' : '❌ No') . '</td>';
                $tabla .= '<td>€' . number_format($fila['comision'] ?? 0, 2, ',', '.') . '</td>';
                $tabla .= '</tr>';
            }

            $tabla .= '</tbody></table>';
            $variables['desglose_reglas'] = $tabla;
        }

        if (!$alcanzaTodos) {
            $variables['meses_consecutivos_sin_minimo'] = static::contarMesesConsecutivos($comercial->id, $año, $mes);
        }

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

    protected static function verificarCondicionesDespido($comercial, $año, $mes, $mesNombre): void
    {
        $config = \App\Models\ConfiguracionComisiones::first();

        if (!$config) {
            return;
        }

        $mesesConsecutivos = static::contarMesesConsecutivos($comercial->id, $año, $mes);
        $mesesAlternos     = static::contarMesesAlternos($comercial->id, $año, $mes, $config->periodo_meses_alternos);

        $cumpleConsecutivos = $mesesConsecutivos >= $config->meses_consecutivos_despido;
        $cumpleAlternos     = $mesesAlternos >= $config->meses_alternos_despido;

        if (!$cumpleConsecutivos && !$cumpleAlternos) {
            return;
        }

        $condicion = $cumpleConsecutivos
            ? "{$mesesConsecutivos} meses consecutivos sin alcanzar mínimo"
            : "{$mesesAlternos} meses alternos sin alcanzar mínimo en {$config->periodo_meses_alternos} meses";

        static::enviarEmailDespido($comercial, $año, $mes, $condicion, $mesesConsecutivos, $mesesAlternos, $config);
    }

    protected static function contarMesesConsecutivos($comercialId, $año, $mes): int
    {
        $consecutivos = 0;
        $fechaActual  = \Carbon\Carbon::create($año, $mes, 1);

        for ($i = 0; $i < 12; $i++) {
            $historial = \App\Models\ComercialHistorialObjetivo::where('comercial_id', $comercialId)
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

    protected static function contarMesesAlternos($comercialId, $año, $mes, $periodoMeses): int
    {
        $fechaFin    = \Carbon\Carbon::create($año, $mes, 1);
        $fechaInicio = $fechaFin->copy()->subMonths($periodoMeses - 1);

        return \App\Models\ComercialHistorialObjetivo::where('comercial_id', $comercialId)
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('año', [$fechaInicio->year, $fechaFin->year]);
            })
            ->where('alcanzo_todos_minimos_obligatorios', false)
            ->count();
    }

    protected static function enviarEmailDespido($comercial, $año, $mes, $condicion, $mesesConsecutivos, $mesesAlternos, $config): void
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
                           . "<p>Meses consecutivos: {$mesesConsecutivos}<br>Meses alternos: {$mesesAlternos}</p>";

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

    protected static function reemplazarVariables(string $texto, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $texto = str_replace('{{' . $key . '}}', $value, $texto);
        }

        return $texto;
    }
}
