<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Models\ContratoResponsabilidad;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;

class ContratosResponsabilidadRelationManager extends RelationManager
{
    protected static string $relationship = 'contratosResponsabilidad';
    protected static ?string $title = 'Docs. de Exoneración';
    

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('asesor.name')
                    ->label('Asesor')
                    ->badge()
                    ->color('info'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->esFirmado() ? 'Firmado' : 'Pendiente')
                    ->color(fn ($state) => $state === 'Firmado' ? 'success' : 'warning'),

                TextColumn::make('signed_at')
                    ->label('Fecha firma')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                Action::make('ver_pdf')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn ($record) => $record->esFirmado() && $record->pdf_path)
                    ->url(fn ($record) => Storage::disk('public')->url($record->pdf_path))
                    ->openUrlInNewTab(),

                Action::make('reenviar_enlace')
                    ->label('Reenviar enlace')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->esFirmado())
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar enlace de firma')
                    ->modalDescription('Se enviará de nuevo el email al cliente con el enlace para firmar.')
                    ->action(function ($record) {
                        $url = route('responsabilidad.show', $record->token);
                        try {
                            \Illuminate\Support\Facades\Mail::to($record->cliente->email_contacto)
                                ->send(new \App\Mail\ContratoResponsabilidadEnviadoMail($record, $url));

                            \Filament\Notifications\Notification::make()
                                ->title('Enlace reenviado')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error al reenviar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('enviar_copia')
                    ->label('Enviar copia')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn ($record) => $record->esFirmado() && $record->pdf_path)
                    ->requiresConfirmation()
                    ->modalHeading('Enviar copia al cliente')
                    ->modalDescription('Se enviará una copia del documento firmado al email del cliente.')
                    ->action(function ($record) {
                        $absolutePdfPath = storage_path('app/public/' . $record->pdf_path);
                        try {
                            \Illuminate\Support\Facades\Mail::to($record->cliente->email_contacto)
                                ->cc($record->asesor->email)
                                ->send(new \App\Mail\ContratoResponsabilidadFirmadoMail($record, $absolutePdfPath));

                            \Filament\Notifications\Notification::make()
                                ->title('Copia enviada al cliente')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error al enviar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin contratos de responsabilidad')
            ->emptyStateDescription('Los contratos generados aparecerán aquí.');
    }
}
