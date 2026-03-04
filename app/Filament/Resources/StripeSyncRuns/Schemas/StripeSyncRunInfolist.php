<?php

namespace App\Filament\Resources\StripeSyncRuns\Schemas;

use App\Filament\Resources\ClienteSuscripcionResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StripeSyncRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Resumen')
                    ->columnSpan(['default' => 12, 'lg' => 6])
                    ->columns(12)
                    ->schema([
                        TextEntry::make('id')->label('Run ID')->columnSpan(2),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (?string $state) => match ($state) {
                                'running'  => 'warning',
                                'finished' => 'success',
                                'failed'   => 'danger',
                                default    => 'gray',
                            })
                            ->columnSpan(2),

                        TextEntry::make('solo_activas')
                            ->label('Solo activas')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Sí' : 'No')
                            ->color(fn ($state) => $state ? 'info' : 'gray')
                            ->columnSpan(2),

                        TextEntry::make('backfill_invoices')
                            ->label('Backfill facturas')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Sí' : 'No')
                            ->color(fn ($state) => $state ? 'info' : 'gray')
                            ->columnSpan(2),

                        TextEntry::make('chunk_size')->label('Chunk')->columnSpan(2),
                        TextEntry::make('user_id')->label('User')->columnSpan(2),

                        TextEntry::make('started_at')->label('Inicio')->dateTime()->columnSpan(4),
                        TextEntry::make('finished_at')->label('Fin')->dateTime()->columnSpan(4),
                        TextEntry::make('created_at')->label('Creado')->dateTime()->columnSpan(4),
                    ]),

                Section::make('Contadores')
                    ->columnSpan(['default' => 12, 'lg' => 6])
                    ->columns(12)
                    ->schema([
                        TextEntry::make('total')->label('Procesadas')->badge()->columnSpan(2),

                        TextEntry::make('subs_updated')
                            ->label('Subs actualizadas')
                            ->badge()
                            ->color('success')
                            ->columnSpan(3),

                        TextEntry::make('subs_no_change')
                            ->label('Sin cambios')
                            ->badge()
                            ->color('gray')
                            ->columnSpan(2),

                        TextEntry::make('skipped_invalid')
                            ->label('Saltadas inválidas')
                            ->badge()
                            ->color('gray')
                            ->columnSpan(2),

                        TextEntry::make('errors')
                            ->label('Errores')
                            ->badge()
                            ->color('danger')
                            ->columnSpan(3),

                        TextEntry::make('inv_created')
                            ->label('Facturas creadas')
                            ->badge()
                            ->color('info')
                            ->columnSpan(3),

                        TextEntry::make('inv_skipped')
                            ->label('Facturas saltadas')
                            ->badge()
                            ->color('gray')
                            ->columnSpan(3),

                        TextEntry::make('inv_errors')
                            ->label('Errores facturas')
                            ->badge()
                            ->color('danger')
                            ->columnSpan(3),
                    ]),

                // ✅ LOGS (sin relationship(), compatible v4)
                Section::make('Logs del run')
                    ->description('Mensajes/errores guardados durante la ejecución.')
                    ->columnSpan(12)
                    ->schema([
                        RepeatableEntry::make('logs')
                            ->label('')
                            ->columns(12)
                            ->getStateUsing(function ($record) {
                                // Espera relación $record->logs() en el modelo StripeSyncRun
                                // Ordenamos DESC para ver lo último arriba
                                $logs = $record->logs()
                                    ->orderByDesc('id')
                                    ->limit(200) // evita cargar infinito
                                    ->get();

                                // RepeatableEntry espera array “plano”
                                return $logs->map(fn ($l) => [
                                    'level' => $l->level,
                                    'cliente_suscripcion_id' => $l->cliente_suscripcion_id,
                                    'stripe_subscription_id' => $l->stripe_subscription_id,
                                    'message' => $l->message,
                                    'context' => $l->context,
                                    'created_at' => $l->created_at,
                                ])->all();
                            })
                            ->schema([
                                TextEntry::make('level')
                                    ->label('Nivel')
                                    ->badge()
                                    ->color(fn (?string $state) => match ($state) {
                                        'info'    => 'info',
                                        'warning' => 'warning',
                                        'error'   => 'danger',
                                        default   => 'gray',
                                    })
                                    ->columnSpan(2),

                                TextEntry::make('cliente_suscripcion_id')
                                    ->label('Suscripción')
                                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                                    ->url(function ($state) {
                                        if (! $state) return null;
                                        return ClienteSuscripcionResource::getUrl('view', ['record' => $state]);
                                    })
                                    ->openUrlInNewTab()
                                    ->columnSpan(2),

                                TextEntry::make('stripe_subscription_id')
                                    ->label('Stripe sub')
                                    ->copyable()
                                    ->columnSpan(3),

                                TextEntry::make('message')
                                    ->label('Mensaje')
                                    ->columnSpan(5),

                                TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->dateTime()
                                    ->columnSpan(3),

                                TextEntry::make('context')
                                    ->label('Contexto')
                                    ->formatStateUsing(function ($state) {
                                        if (empty($state)) return '—';
                                        if (is_string($state)) return $state;
                                        return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    })
                                    ->columnSpan(9),
                            ]),
                    ]),
            ]);
    }
}
