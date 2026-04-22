<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComercialResource\Pages\ListComercials;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ComercialResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null   $navigationGroup = 'Comisiones';
    protected static ?string $navigationLabel                 = 'Comerciales';
    protected static ?string $modelLabel                      = 'Comercial';
    protected static ?string $pluralModelLabel                = 'Comerciales';
    protected static ?string $slug                            = 'comisiones-comerciales';
    protected static ?int    $navigationSort                  = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'coordinador']) ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn ($q) => $q->where('name', 'comercial'))
            ->withCount('asignacionesReglas')
            ->with('asignacionesReglas.regla');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reglas_detalle')
                    ->label('Reglas asignadas')
                    ->html()
                    ->getStateUsing(function (User $record): string {
                        $reglas = $record->asignacionesReglas;

                        if ($reglas->isEmpty()) {
                            return '<span class="text-xs text-gray-400 italic">Sin reglas</span>';
                        }

                        $total   = $reglas->count();
                        $activas = $reglas->where('activa', true)->count();

                        // Filas del detalle expandido
                        $filas = $reglas->map(function ($asig) {
                            $regla = $asig->regla;
                            if (!$regla) return '';

                            $estadoBadge = $asig->activa
                                ? '<span style="background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:4px;font-size:10px;">Activa</span>'
                                : '<span style="background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:4px;font-size:10px;">Inactiva</span>';

                            $obligBadge = $asig->es_obligatoria
                                ? '<span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:4px;font-size:10px;">Obligatoria</span>'
                                : '';

                            $minimo    = number_format($regla->minimo_mensual ?? 0, 2, ',', '.') . ' €';
                            $comision  = ($regla->porcentaje_comision ?? 0) . ' %';

                            return "
                                <tr style='border-top:1px solid #f3f4f6;'>
                                    <td style='padding:4px 8px 4px 0;font-size:11px;font-weight:600;color:#1f2937;white-space:nowrap;'>
                                        {$regla->nombre}
                                    </td>
                                    <td style='padding:4px 8px;font-size:11px;color:#6b7280;white-space:nowrap;'>
                                        Mín. {$minimo}
                                    </td>
                                    <td style='padding:4px 8px;font-size:11px;color:#6b7280;white-space:nowrap;'>
                                        {$comision}
                                    </td>
                                    <td style='padding:4px 0 4px 8px;white-space:nowrap;'>
                                        {$estadoBadge} {$obligBadge}
                                    </td>
                                </tr>
                            ";
                        })->implode('');

                        $resumen = $activas === $total
                            ? "{$total} regla" . ($total !== 1 ? 's' : '')
                            : "{$activas} activa" . ($activas !== 1 ? 's' : '') . " / {$total} total";

                        return "
                            <details style='cursor:pointer;'>
                                <summary style='font-size:12px;font-weight:600;color:#1e40af;list-style:none;display:flex;align-items:center;gap:6px;'>
                                    <span style='display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:#dbeafe;color:#1e40af;border-radius:50%;font-size:11px;font-weight:700;'>{$total}</span>
                                    {$resumen}
                                    <span style='font-size:10px;color:#9ca3af;margin-left:2px;'>▼</span>
                                </summary>
                                <div style='margin-top:6px;padding:6px 10px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;'>
                                    <table style='border-collapse:collapse;width:100%;'>
                                        <tbody>{$filas}</tbody>
                                    </table>
                                </div>
                            </details>
                        ";
                    })
                    ->wrap(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('gestionar_reglas')
                    ->label('')
                    ->tooltip('Gestionar reglas de comisión')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->url(function (User $record) {
                        $trabajador = \App\Models\Trabajador::where('user_id', $record->id)->first();
                        if (!$trabajador) {
                            return null;
                        }
                        return \App\Filament\Resources\TrabajadorResource::getUrl('edit', ['record' => $trabajador->id]);
                    })
                    ->visible(fn (User $record) => \App\Models\Trabajador::where('user_id', $record->id)->exists())
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::exportarExcelAction(),
                    static::exportarPdfAction(),
                ]),
            ]);
    }

    // ─────────────────────────────────────────────
    // COLUMNAS DISPONIBLES PARA EXPORT
    // ─────────────────────────────────────────────
    private static function columnasExport(): array
    {
        return [
            'name'                 => 'Nombre',
            'email'                => 'Email',
            'num_reglas'           => 'Nº de reglas',
            'reglas_nombres'       => 'Reglas activas',
            'reglas_obligatorias'  => 'Reglas obligatorias',
            'minimo_mensual_total' => 'Mínimo mensual total (€)',
            'comision_media'       => 'Comisión media (%)',
        ];
    }

    // ─────────────────────────────────────────────
    // BULK ACTION: EXCEL
    // ─────────────────────────────────────────────
    private static function exportarExcelAction(): BulkAction
    {
        return BulkAction::make('exportar_excel')
            ->label('Exportar Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->form([
                CheckboxList::make('columnas')
                    ->label('Selecciona las columnas a incluir')
                    ->options(static::columnasExport())
                    ->default(array_keys(static::columnasExport()))
                    ->bulkToggleable()
                    ->columns(2)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $token = Str::uuid()->toString();
                cache()->put("comercial_export_{$token}", [
                    'ids'     => $records->pluck('id')->toArray(),
                    'columnas' => $data['columnas'],
                ], now()->addMinutes(5));

                return redirect()->route('comisiones.comerciales.export-excel', ['token' => $token]);
            })
            ->deselectRecordsAfterCompletion();
    }

    // ─────────────────────────────────────────────
    // BULK ACTION: PDF
    // ─────────────────────────────────────────────
    private static function exportarPdfAction(): BulkAction
    {
        return BulkAction::make('exportar_pdf')
            ->label('Exportar PDF')
            ->icon('heroicon-o-document-text')
            ->color('danger')
            ->form([
                CheckboxList::make('columnas')
                    ->label('Selecciona las columnas a incluir')
                    ->options(static::columnasExport())
                    ->default(array_keys(static::columnasExport()))
                    ->bulkToggleable()
                    ->columns(2)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $token = Str::uuid()->toString();
                cache()->put("comercial_export_{$token}", [
                    'ids'      => $records->pluck('id')->toArray(),
                    'columnas' => $data['columnas'],
                ], now()->addMinutes(5));

                return redirect()->route('comisiones.comerciales.export-pdf', ['token' => $token]);
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComercials::route('/'),
        ];
    }
}
