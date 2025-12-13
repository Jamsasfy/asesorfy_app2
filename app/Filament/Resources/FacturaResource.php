<?php

namespace App\Filament\Resources;

use App\Enums\FacturaEstadoEnum;
use App\Enums\VentaCorreccionEstadoEnum;
use App\Filament\Resources\FacturaResource\Pages;
use App\Models\Factura;
use App\Models\Servicio;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use App\Services\ConfiguracionService;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Action;
use Filament\Forms\Components\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder; 
use Filament\Tables\Actions\Action as TablesAction;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;


class FacturaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Factura::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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



public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Cabecera de la Factura')->columns(4)->schema([
                Select::make('cliente_id')
                    ->relationship('cliente', 'razon_social')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2),

                TextInput::make('numero_factura')
                    ->label('Número de Factura')
                    ->readOnly() // Lo hacemos de solo lectura para evitar errores
                    ->placeholder('Se generará al guardar'),

                 Select::make('estado')
                        ->options(FacturaEstadoEnum::class) // <-- ¡Directamente el Enum!
                        ->required()
                        ->default(FacturaEstadoEnum::PENDIENTE_PAGO),

                DatePicker::make('fecha_emision')
                    ->required()
                    ->default(now()),

                DatePicker::make('fecha_vencimiento')
                    ->required(),
            ]),

            Section::make('Líneas de la Factura')->schema([
                Repeater::make('items')
                    ->relationship()
                    ->live()
                    ->default([
                        ['cantidad' => 1, 'precio_unitario' => 0, 'porcentaje_iva' => 21],
                    ])
                    ->columns(12)
                    ->afterStateHydrated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set))
                     ->addAction(fn ($action) =>
                            $action
                                ->label('Añadir Línea o actualizar totales')
                                ->after(fn (Get $get, Set $set) => self::recalcularTotales($get, $set))
                        )
                            ->helperText('Añade todas las líneas de la factura antes de guardar. Si metes solo un item o servicio, debes pulsar en "Añadir Linea o actualizar totales" para que se actualice la factura en base de datos correctamente.')

                        ->deleteAction(fn ($action) =>
                            $action->after(fn (Get $get, Set $set) => self::recalcularTotales($get, $set))
                        )
                    ->schema([
                          Select::make('servicio_id')
        ->label('Servicio')
        ->relationship('servicio', 'nombre') 
        ->searchable() 
        ->preload()
        ->nullable()
        ->reactive()
        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
            // Esta lógica es para edición/creación, no para la visualización pasiva.
            if (!$state) {
                $set('descripcion', null);
                $set('precio_unitario', 0);
                $set('porcentaje_iva', 21);
                self::recalcularTotales($get, $set);
                return;
            }
            $servicio = \App\Models\Servicio::find($state); // Asegúrate de importar \App\Models\Servicio
            if ($servicio) {
                $set('descripcion', $servicio->nombre);
                $set('precio_unitario', $servicio->precio_base);
                $set('porcentaje_iva', 21);
            }
            self::recalcularTotales($get, $set);
        })
        ->columnSpan(4),

    // --- BLOQUE DE DEPURACIÓN TEMPORAL: Placeholder para mostrar el nombre del servicio ---
    // Este Placeholder aparecerá solo en modo "Ver"
    \Filament\Forms\Components\Placeholder::make('servicio_nombre_directo')
        ->label('Nombre del Servicio (VERIFICACIÓN)')
        ->content(function (\App\Models\FacturaItem $record) { // El $record aquí es la instancia de FacturaItem
            // Intentamos acceder directamente al nombre del servicio a través de la relación.
            // Si $record->servicio es null (relación no cargada o ID no válido),
            // o si $record->servicio->nombre es null, mostrará un mensaje.
            
            // Puedes añadir un dd() aquí si quieres ver el record completo
            // dd($record); 

            return $record->servicio->nombre ?? 'Servicio no encontrado o ID nulo'; 
        })
        // Hazlo visible solo en la operación de 'view'
        ->visible(fn (string $operation): bool => $operation === 'view')
        ->columnSpan(4),

                        TextInput::make('descripcion')
                            ->required()
                            ->columnSpan(8),

                        TextInput::make('cantidad')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->columnSpan(4)
                            ->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set)),

                        TextInput::make('precio_unitario')
                            ->label('Precio Unit.')
                            ->numeric()
                            ->required()
                            ->columnSpan(4)
                            ->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set)),

                        TextInput::make('porcentaje_iva')
                            ->label('% IVA')
                            ->numeric()
                            ->required()
                           ->default(fn () => ConfiguracionService::get('IVA_general', 21.00)) 
                            ->columnSpan(4)
                            ->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set)),
                    ]),
            ]),

            Section::make('Totales')->columns(3)->schema([
                TextInput::make('base_imponible')
                    ->readOnly()
                    ->numeric()
                    ->prefix('€')
                    ->default(0),

                TextInput::make('total_iva')
                    ->label('Total IVA')
                    ->readOnly()
                    ->numeric()
                    ->prefix('€')
                    ->default(0),

                TextInput::make('total_factura')
                    ->label('TOTAL')
                    ->readOnly()
                    ->numeric()
                    ->prefix('€')
                    ->default(0),
            ]),
             Hidden::make('mostrar_guardar')->default(false),
        ]);
}





protected static function calcularTotalesDesdeItems(array $items): array
{
    $baseImponibleTotal = 0;
    $ivaTotalAcumulado = 0;

    foreach ($items as $item) {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $precioUnitario = (float)($item['precio_unitario'] ?? 0);
        $porcentajeIva = (float)($item['porcentaje_iva'] ?? 0);

        $subtotalItem = $cantidad * $precioUnitario;
        $ivaDelItem = $subtotalItem * ($porcentajeIva / 100);

        $baseImponibleTotal += $subtotalItem;
        $ivaTotalAcumulado += $ivaDelItem;
    }

    return [
        'base_imponible' => round($baseImponibleTotal, 2),
        'total_iva' => round($ivaTotalAcumulado, 2),
        'total_factura' => round($baseImponibleTotal + $ivaTotalAcumulado, 2),
    ];
}




public static function recalcularTotales(Get $get, Set $set): void
{
    $items = $get('items');
    if (!is_array($items)) {
        return;
    }

    $baseImponibleTotal = 0;
    $ivaTotalAcumulado = 0;

    foreach ($items as $item) {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $precioUnitario = (float)($item['precio_unitario'] ?? 0);
        $porcentajeIva = (float)($item['porcentaje_iva'] ?? 0); // Ej: 21, no 0.21

        $subtotalItem = $cantidad * $precioUnitario;
        $ivaDelItem = $subtotalItem * ($porcentajeIva / 100);

        $baseImponibleTotal += $subtotalItem;
        $ivaTotalAcumulado += $ivaDelItem;
    }

    $set('base_imponible', round($baseImponibleTotal, 2));
    $set('total_iva', round($ivaTotalAcumulado, 2));
    $set('total_factura', round($baseImponibleTotal + $ivaTotalAcumulado, 2));
}


    public static function table(Table $table): Table
    {
        return $table
          ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('numero_factura')
                    ->label('Nº Factura') // Etiqueta más legible
                    ->searchable()
                    ->sortable()
                    ->copyable() // Permite copiar el número con un clic
                    ->copyMessage('Número de factura copiado al portapapeles.'),

                TextColumn::make('cliente.razon_social')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Factura $record): string => $record->cliente->cif ?? ''), // Muestra el CIF del cliente debajo
                
                TextColumn::make('fecha_emision')
                    ->label('Emisión') // Etiqueta más corta y clara
                    ->date('d/m/Y')
                    ->sortable(),
                
              TextColumn::make('fecha_vencimiento')
                ->label('Vencimiento')
                ->date('d/m/Y')
                ->sortable()
                // CAMBIO AQUÍ: El tipo de retorno de la closure ahora es 'string|null'
                ->color(fn (Factura $record): string|null => 
                    ($record->estado === FacturaEstadoEnum::PENDIENTE_PAGO && $record->fecha_vencimiento?->isPast()) 
                    ? 'danger' 
                    : null // Cuando no se cumple la condición, retorna null
                ),
                  TextColumn::make('base_imponible')
                ->label('Base Imponible')
                ->money('EUR')
                ->sortable()
                ->alignEnd(), // Alinea a la derecha para números

            TextColumn::make('total_iva')
                ->label('IVA Total')
                ->money('EUR')
                ->sortable()
                ->alignEnd(), // Alinea a la derecha para números    
                TextColumn::make('total_factura')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable()
                    ->color('primary') // Un color para destacar el total
                    ->alignEnd(), // Alinea el texto a la derecha para números
                
                TextColumn::make('estado')
                    ->label('Estado') // Etiqueta del badge
                    ->badge(), // Utilizará HasLabel y HasColor de tu Enum
                TextColumn::make('created_at')
                ->label('Factura creada')
                    ->dateTime('d/m/y - H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                // Filtro por Estado (con nuestro Enum)
                SelectFilter::make('estado')
                    ->options(FacturaEstadoEnum::class)
                    ->label('Estado'),
                
                // Filtro por Cliente
                SelectFilter::make('cliente_id')
                    ->relationship('cliente', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->label('Cliente'),

                // Filtro por Rango de Fechas de Emisión
                Tables\Filters\Filter::make('fecha_emision')
                   
                    ->form([
                        DatePicker::make('fecha_desde')
                            ->label('Fecha de Emisión Desde')
                            ->native(false),
                        DatePicker::make('fecha_hasta')
                            ->label('Fecha de Emisión Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'],
                                fn (Builder $query, $date): Builder => $query->whereDate('fecha_emision', '>=', $date),
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn (Builder $query, $date): Builder => $query->whereDate('fecha_emision', '<=', $date),
                            );
                    })
                    ->label('Rango de Emisión'),
                      Tables\Filters\Filter::make('con_correccion_solicitada')
        ->label('Con Corrección Solicitada')
        ->query(fn (Builder $query): Builder => 
            $query->whereHas('venta', fn (Builder $q) => 
                $q->where('correccion_estado', VentaCorreccionEstadoEnum::SOLICITADA)
            )
        )
        ->toggle(),
            ],layout: FiltersLayout::AboveContent)
            ->actions([
                // Ver Factura (siempre visible)
                Tables\Actions\ViewAction::make()
                    ->label('') // Solo icono
                    ->tooltip('Ver Detalles'), // Tooltip para indicar la acción
                
                // Acción de Pagar Factura (simulada o para enlazar a Stripe/pago manual)
                Tables\Actions\Action::make('marcar_pagada')
                    ->label('') // Solo icono
                    ->tooltip('Marcar como Pagada')
                    ->icon('heroicon-o-currency-euro')
                    ->color('success')
                    ->visible(fn (Factura $record): bool => 
                        $record->estado === FacturaEstadoEnum::PENDIENTE_PAGO || $record->estado === FacturaEstadoEnum::IMPAGADA
                    )
                    ->requiresConfirmation() // Pide confirmación antes de cambiar el estado
                    ->action(function (Factura $record) {
                        $record->update(['estado' => FacturaEstadoEnum::PAGADA]);
                        Notification::make()
                            ->title('Factura marcada como pagada.')
                            ->success()
                            ->send();
                    }),

                // Acción de Anular Factura
                Tables\Actions\Action::make('anular_factura')
                    ->label('') // Solo icono
                    ->tooltip('Anular Factura')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Factura $record): bool => 
                        $record->estado !== FacturaEstadoEnum::PAGADA && $record->estado !== FacturaEstadoEnum::ANULADA
                    ) // Solo si no está ya pagada o anulada
                    ->requiresConfirmation()
                    ->action(function (Factura $record) {
                        $record->update(['estado' => FacturaEstadoEnum::ANULADA]);
                        Notification::make()
                            ->title('Factura anulada.')
                            ->success()
                            ->send();
                    }),
                     // --- ¡NUEVA ACCIÓN: Generar PDF! ---
           Tables\Actions\Action::make('generar_pdf')
                ->label('') // No queremos texto, solo el icono
                ->tooltip('Generar PDF') // Tooltip al pasar el ratón
                ->icon('heroicon-o-document-arrow-down') // Icono de descarga o documento
                ->color('info') // Un color azul para la acción
                // Definimos la URL a la que se dirigirá al hacer clic
                ->url(fn (Factura $record): string => route('facturas.generar-pdf', $record)) // <-- Puntos importantes
                ->openUrlInNewTab(), // <-- Abrir en nueva pestaña

                // Si necesitas una acción de "Rectificar" o "Reclamar", iría aquí
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Acción masiva para marcar como Pagada
                    Tables\Actions\BulkAction::make('marcar_pagadas_seleccionadas')
                        ->label('Marcar como Pagadas')
                        ->icon('heroicon-o-currency-euro')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(fn (Factura $factura) => 
                                $factura->estado = FacturaEstadoEnum::PAGADA
                            );
                            $records->each->save(); // Guardar los cambios
                            Notification::make()->title('Facturas marcadas como pagadas.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    
                    // Acción masiva para Anular
                    Tables\Actions\BulkAction::make('anular_seleccionadas')
                        ->label('Anular seleccionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each(fn (Factura $factura) => 
                                $factura->estado = FacturaEstadoEnum::ANULADA
                            );
                            $records->each->save(); // Guardar los cambios
                            Notification::make()->title('Facturas anuladas.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Eliminamos DeleteBulkAction si preferimos anular
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
            //->defaultSort('fecha_emision', 'desc'); // Ordenar por fecha de emisión descendente por defecto
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
            'index' => Pages\ListFacturas::route('/'),
            'create' => Pages\CreateFactura::route('/create'),
            'edit' => Pages\EditFactura::route('/{record}/edit'),
        ];
    }
}
