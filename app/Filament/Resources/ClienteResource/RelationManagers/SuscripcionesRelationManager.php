<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use App\Filament\Resources\VentaResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\ClienteSuscripcion;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Filament\Resources\ClienteSuscripcionResource;
use App\Filament\Resources\ProyectoResource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Database\Eloquent\Builder;

class SuscripcionesRelationManager extends RelationManager
{
    protected static string $relationship = 'suscripciones';
    protected static ?string $title = 'Suscripciones y Servicios Contratados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('servicio.nombre')
            ->columns([
                // Columna del Servicio (con enlace condicional a su Proyecto)
              TextColumn::make('nombre_final') // <-- CAMBIO AQUÍ
                    ->label('Servicio')
                    ->icon(function (ClienteSuscripcion $record): ?string {
                        // Tu lógica para el icono se queda igual
                        return $record->ventaOrigen?->proyectos()
                            ->where('servicio_id', $record->servicio_id)
                            ->exists() ? 'heroicon-s-briefcase' : null;
                    })
                    ->url(function (ClienteSuscripcion $record): ?string {
                        // Tu lógica para la URL se queda igual
                        $proyecto = $record->ventaOrigen?->proyectos()
                            ->where('servicio_id', $record->servicio_id)
                            ->first();
                        return $proyecto ? ProyectoResource::getUrl('view', ['record' => $proyecto]) : null;
                    }, true)
                    ->color(function (ClienteSuscripcion $record): string {
                        // Tu lógica para el color se queda igual
                        $tieneProyecto = $record->ventaOrigen?->proyectos()
                            ->where('servicio_id', $record->servicio_id)
                            ->exists();
                        if ($tieneProyecto) {
                            return 'primary';
                        }
                        if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                            return 'warning';
                        }
                        return 'gray';
                    }),
                // ▼▼▼ LA COLUMNA CORREGIDA ▼▼▼
TextColumn::make('contexto_servicio')
    ->label('Estado del proyecto')
    ->badge()
    ->placeholder('N/A') // 1. Muestra esto si el estado es nulo
    ->state(function (ClienteSuscripcion $record) {
        $proyecto = $record->ventaOrigen?->proyectos()
            ->where('servicio_id', $record->servicio_id)->first();
        
        if ($proyecto) {
            return $proyecto->estado;
        }

        if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
            return 'Recurrente';
        }
        
        return null;
    })
    ->color(fn ($state): string => match ($state) {
        ProyectoEstadoEnum::Pendiente => 'warning',
        ProyectoEstadoEnum::EnProgreso => 'primary',
        ProyectoEstadoEnum::Finalizado => 'success',
        ProyectoEstadoEnum::Cancelado => 'danger',
        'Recurrente' => 'info',
        default => 'gray',
    })
    ->formatStateUsing(fn ($state) => is_string($state) ? $state : $state?->getLabel()),

                // Columna del Estado (SIEMPRE el de la suscripción)
                TextColumn::make('estado')
                    ->label('Estado de la Suscripción/Facturacón')
                    ->badge()
                    ->formatStateUsing(fn (ClienteSuscripcionEstadoEnum $state) => $state->getLabel())
                    ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
                        ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                        ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                        ClienteSuscripcionEstadoEnum::CANCELADA, ClienteSuscripcionEstadoEnum::FINALIZADA => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('precio_acordado')->label('Precio')->money('eur')
                ->formatStateUsing(function ($state, ClienteSuscripcion $record): string {
                    // Formateamos el precio base
                    $precio = number_format($state, 2, ',', '.');
                    
                    // Si es recurrente, añadimos la periodicidad
                    if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                        $periodicidad = $record->ciclo_facturacion?->value ?? 'recurrente';
                        return "{$precio} € / {$periodicidad}";
                    }

                    // Si no, solo devolvemos el precio
                    return "{$precio} €";
                }),
                TextColumn::make('fecha_inicio')->label('Inicio')->date('d/m/Y'),
            ])
            ->recordActions([
               ViewAction::make()
    ->url(fn (ClienteSuscripcion $record): string => ClienteSuscripcionResource::getUrl('view', ['record' => $record]))
    ->openUrlInNewTab(),
                Action::make('ver_venta')
                    ->label('Ver Venta')
                    ->icon('heroicon-o-shopping-cart')
                    ->url(fn(ClienteSuscripcion $record) => VentaResource::getUrl('view', ['record' => $record->venta_origen_id]))
                    ->openUrlInNewTab()
                    ->color('gray'),
            ]);
    }
}