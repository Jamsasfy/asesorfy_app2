<?php

namespace App\Filament\Portal\Resources\Facturas\Schemas;

use App\Enums\FacturaEstadoEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class FacturaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cliente_id')
                    ->relationship('cliente', 'id')
                    ->required(),
                Select::make('venta_id')
                    ->relationship('venta', 'id'),
                TextInput::make('serie')
                    ->required(),
                TextInput::make('numero_factura')
                    ->required(),
                DatePicker::make('fecha_emision')
                    ->required(),
                DatePicker::make('fecha_vencimiento')
                    ->required(),
                Select::make('estado')
                    ->options(FacturaEstadoEnum::class)
                    ->default('pendiente_pago')
                    ->required(),
                Select::make('metodo_pago')
                    ->options([
            'transferencia' => 'Transferencia',
            'domiciliacion' => 'Domiciliacion',
            'stripe' => 'Stripe',
            'otro' => 'Otro',
        ]),
                TextInput::make('stripe_invoice_id'),
                TextInput::make('stripe_payment_intent_id'),
                TextInput::make('base_imponible')
                    ->required()
                    ->numeric(),
                TextInput::make('total_iva')
                    ->required()
                    ->numeric(),
                TextInput::make('total_factura')
                    ->required()
                    ->numeric(),
                Textarea::make('observaciones_publicas')
                    ->columnSpanFull(),
                Textarea::make('observaciones_privadas')
                    ->columnSpanFull(),
                TextInput::make('factura_rectificada_id')
                    ->numeric(),
                Textarea::make('motivo_rectificacion')
                    ->columnSpanFull(),
            ]);
    }
}
