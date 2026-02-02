<?php

namespace App\Filament\Portal\Resources\Facturas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FacturaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('cliente.id')
                    ->label('Cliente'),
                TextEntry::make('venta.id')
                    ->label('Venta')
                    ->placeholder('-'),
                TextEntry::make('serie'),
                TextEntry::make('numero_factura'),
                TextEntry::make('fecha_emision')
                    ->date(),
                TextEntry::make('fecha_vencimiento')
                    ->date(),
                TextEntry::make('estado')
                    ->badge(),
                TextEntry::make('metodo_pago')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('stripe_invoice_id')
                    ->placeholder('-'),
                TextEntry::make('stripe_payment_intent_id')
                    ->placeholder('-'),
                TextEntry::make('base_imponible')
                    ->numeric(),
                TextEntry::make('total_iva')
                    ->numeric(),
                TextEntry::make('total_factura')
                    ->numeric(),
                TextEntry::make('observaciones_publicas')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('observaciones_privadas')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('factura_rectificada_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('motivo_rectificacion')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
