<?php

namespace App\Filament\Widgets;

use App\Models\Venta;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class ComercialConversionesTable extends BaseWidget
{
    protected static ?string $heading = 'Últimas Conversiones';
    protected int | string | array $columnSpan = 'full';
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return Auth::user()?->can('View:ComercialConversionesTable') ?? false;
    }

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate   = $this->filters['endDate'] ?? now()->toDateString();

        return $table
            ->query(
                Venta::query()
                    ->whereHas('lead', function (Builder $query) {
                        $query->where('asignado_id', Auth::id());
                    })
                    ->where('estado', 'completada')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Venta')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->url(fn ($record) => \App\Filament\Resources\VentaResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('lead.nombre')
                    ->label('Lead')
                    ->searchable()
                    ->limit(30)
                    ->description(fn ($record) => $record->lead?->email)
                    ->url(fn ($record) => $record->lead_id ? \App\Filament\Resources\LeadResource::getUrl('view', ['record' => $record->lead_id]) : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('cliente.razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('importe_total')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lead.estado')
                    ->label('Estado Lead')
                    ->badge()
                    ->color(fn ($state) => match($state?->value ?? null) {
                        'convertido_activado' => 'success',
                        'convertido_firmado'  => 'warning',
                        default               => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
