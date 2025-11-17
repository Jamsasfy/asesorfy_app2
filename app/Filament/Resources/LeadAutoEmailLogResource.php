<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadAutoEmailLogResource\Pages;
use App\Filament\Resources\LeadResource;
use App\Models\LeadAutoEmailLog;
use Filament\Forms\Form;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;


class LeadAutoEmailLogResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = LeadAutoEmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';
    protected static ?string $navigationGroup = 'Comunicación';
    protected static ?string $navigationLabel = 'Log Envíos  Boot IA Fy🤖';
    protected static ?string $modelLabel = 'Log envío automático 🤖';
    protected static ?string $pluralModelLabel = 'Log envíos automáticos 🤖';

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
        // No queremos crear/editar desde aquí,
        // solo ver (List + View).
        return $form->schema([]);
    }

    /**
     * ======================
     *   LISTADO (TABLE)
     * ======================
     */
    public static function table(Table $table): Table
    {
        return $table
           ->defaultSort('sent_at', 'desc')   // Primero por sent_at DESC
        ->defaultSort('created_at', 'desc') // Luego por created_at DESC
            ->columns([
                Tables\Columns\TextColumn::make('lead.nombre')
                    ->label('Lead')
                     ->searchable(isIndividual: true)
                    ->sortable()
                    ->url(fn ($record) => LeadResource::getUrl('edit', ['record' => $record->lead]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('lead.email')
                    ->label('Email')
                    ->searchable(isIndividual: true)
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado lead')
                    ->sortable(),

                Tables\Columns\TextColumn::make('intento')
                    ->label('Intento')
                    ->sortable(),

                Tables\Columns\TextColumn::make('template_identifier')
                    ->label('Plantilla')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado envío')
                    ->colors([
                        'success' => 'sent',
                        'warning' => 'pending',
                        'danger'  => 'failed',
                        'info'    => 'rate_limited',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Programado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Proveedor')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('rate_limited')
                    ->label('Rate limited')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('error_code')
                    ->label('Error')
                    ->limit(20)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado lead')
                    ->options(fn () => LeadAutoEmailLog::query()
                        ->select('estado')
                        ->distinct()
                        ->pluck('estado', 'estado')
                        ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado envío')
                    ->options([
                        'pending'      => 'Pendiente',
                        'sent'         => 'Enviado',
                        'failed'       => 'Fallido',
                        'skipped'      => 'Omitido',
                        'rate_limited' => 'Rate limited',
                    ]),

                Tables\Filters\TernaryFilter::make('rate_limited')
                    ->label('Solo rate-limited'),

                Tables\Filters\Filter::make('sent_between')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('sent_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('sent_at', '<=', $date));
                    }),
                      
                  Tables\Filters\Filter::make('sent_today')
                        ->label('Enviados hoy')
                        ->toggle()
                        ->query(fn (Builder $query): Builder =>
                            $query->whereDate('sent_at', today())
                        ),
            ], layout: FiltersLayout::AboveContent) // Mantener layout
        ->filtersFormColumns(5)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    /**
     * ======================
     *   VISTA (INFOLIST)
     * ======================
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Lead relacionado')
                    ->description('Información del lead al que pertenece este envío automático.')
                    ->schema([
                        TextEntry::make('lead.nombre')
                            ->label('Nombre')
                            ->url(fn ($record) => LeadResource::getUrl('edit', ['record' => $record->lead]))
                            ->openUrlInNewTab()
                            ->extraAttributes(['class' => 'text-primary font-semibold']),

                        TextEntry::make('lead.email')
                            ->label('Email'),

                        TextEntry::make('estado')
                            ->label('Estado del lead')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'intento_contacto'      => 'warning',
                                'esperando_informacion' => 'info',
                                default                 => 'gray',
                            }),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Detalles del envío')
                    ->schema([
                        TextEntry::make('intento')
                            ->label('Intento')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('template_identifier')
                            ->label('Plantilla usada'),

                        TextEntry::make('subject')
                            ->label('Asunto')
                            ->extraAttributes(['class' => 'font-semibold']),

                        TextEntry::make('status')
                            ->label('Estado del envío')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'sent'         => 'success',
                                'failed'       => 'danger',
                                'rate_limited' => 'info',
                                'pending'      => 'warning',
                                default        => 'gray',
                            }),

                        TextEntry::make('scheduled_at')
                            ->label('Programado para')
                            ->dateTime(),

                        TextEntry::make('sent_at')
                            ->label('Enviado el')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Contenido del email')
                    ->schema([
                        TextEntry::make('body_preview')
                            ->label('Vista previa')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Información técnica')
                    ->schema([
                        TextEntry::make('mail_driver')
                            ->label('Mail Driver')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('provider')
                            ->label('Proveedor')
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),

                        TextEntry::make('provider_message_id')
                            ->label('Message ID')
                            ->placeholder('—'),

                        IconEntry::make('rate_limited')
                            ->label('Rate limited')
                            ->boolean()
                            ->trueIcon('heroicon-o-shield-exclamation')
                            ->falseIcon('heroicon-o-check-circle'),

                        TextEntry::make('error_code')
                            ->label('Código error')
                            ->placeholder('—'),

                        TextEntry::make('error_message')
                            ->label('Mensaje error')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Meta adicional')
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->label('Meta')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->meta))
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeadAutoEmailLogs::route('/'),
            'view'  => Pages\ViewLeadAutoEmailLog::route('/{record}'),
        ];
    }
}
