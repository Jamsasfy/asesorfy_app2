<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Filament\Resources\LeadAutoEmailLogResource\Pages\ListLeadAutoEmailLogs;
use App\Filament\Resources\LeadAutoEmailLogResource\Pages\ViewLeadAutoEmailLog;
use App\Filament\Resources\LeadAutoEmailLogResource\Pages;
use App\Filament\Resources\LeadResource;
use App\Models\LeadAutoEmailLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Support\Enums\FontFamily;

class LeadAutoEmailLogResource extends Resource
{
    protected static ?string $model = LeadAutoEmailLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Comunicación';
    protected static ?string $navigationLabel = 'Historial de Envíos 🤖';
    protected static ?string $modelLabel = 'Envío';
    protected static ?string $pluralModelLabel = 'Historial de Envíos';


    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent'         => 'Enviado',
                        'pending'      => 'Pendiente',
                        'failed'       => 'Fallido',
                        'rate_limited' => 'Límite excedido',
                        'skipped'      => 'Omitido',
                        default        => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent'         => 'success',
                        'pending'      => 'warning',
                        'failed'       => 'danger',
                        'rate_limited' => 'info',
                        'skipped'      => 'gray',
                        default        => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'sent'         => 'heroicon-m-check-circle',
                        'failed'       => 'heroicon-m-x-circle',
                        'pending'      => 'heroicon-m-clock',
                        'rate_limited' => 'heroicon-m-exclamation-triangle',
                        default        => 'heroicon-m-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('lead.nombre')
                    ->label('Destinatario')
                    ->weight('bold')
                    ->description(fn (LeadAutoEmailLog $record) => $record->lead?->email)
                    ->searchable(['nombre', 'email'])
                    ->url(fn ($record) => $record->lead ? LeadResource::getUrl('edit', ['record' => $record->lead]) : null)
                    ->openUrlInNewTab()
                    ->color('primary'),

                TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(40)
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('template_identifier')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => Str::headline(str_replace('_', ' ', $state)))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'sent' => 'Enviados',
                        'failed' => 'Fallidos',
                        'pending' => 'Pendientes',
                    ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['hasta'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make()->label('')->tooltip('Ver detalle'),
            ]);
    }
public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                
                // --- CABECERA DE ESTADO ---
                Section::make()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('status')
                                ->label('Estado Actual')
                                ->badge()
                                ->size('lg')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'sent' => 'ENVIADO',
                                    'failed' => 'FALLIDO',
                                    'pending' => 'PENDIENTE',
                                    default => strtoupper($state),
                                })
                                ->color(fn (string $state): string => match ($state) {
                                    'sent' => 'success',
                                    'failed' => 'danger',
                                    'pending' => 'warning',
                                    default => 'gray',
                                }),

                            TextEntry::make('sent_at')
                                ->label('Fecha Envío')
                                ->dateTime('d/m/Y H:i:s'),

                            TextEntry::make('lead.nombre')
                                ->label('Destinatario')
                                ->url(fn ($record) => $record->lead ? LeadResource::getUrl('edit', ['record' => $record->lead]) : null)
                                ->color('primary')
                                ->weight('bold'),
                            
                            TextEntry::make('template_identifier')
                                ->label('Plantilla')
                                ->badge()
                                ->color('gray'),
                        ]),
                    ]),

                // --- SI HAY ERROR ---
                Section::make('Error')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(fn ($record) => $record->status === 'failed' || $record->error_message)
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Mensaje')
                            ->color('danger')
                            ->weight('bold')
                            ->columnSpanFull(),
                    ]),

                // --- CONTENIDO ---
              // --- CONTENIDO DEL EMAIL ---
                Section::make('Contenido del Mensaje')
                    ->icon('heroicon-m-envelope-open')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Asunto')
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('body_preview')
                            ->label('') // Quitamos etiqueta para ganar espacio
                            ->html()    // 👈 IMPORTANTE: Renderizar como HTML, no Markdown
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => '
                                    p-6 rounded-lg border
                                    bg-white text-gray-900 border-gray-200 
                                    dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700
                                    prose max-w-none 
                                ',
                                // Esto asegura que si el HTML es complejo, no rompa el layout
                                'style' => 'font-family: sans-serif; line-height: 1.5;',
                            ]),
                    ]),

                // --- DATOS TÉCNICOS ---
                Section::make('Información Técnica Avanzada')
                    ->icon('heroicon-m-cpu-chip')
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('mail_driver')
                                ->label('Driver')
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('provider')
                                ->label('Proveedor')
                                ->placeholder('-'),

                            TextEntry::make('intento')
                                ->label('Intento Nº'),

                            TextEntry::make('scheduled_at')
                                ->label('Programado')
                                ->dateTime(),
                            
                            TextEntry::make('provider_message_id')
                                ->label('ID Mensaje')
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),
                            
                            IconEntry::make('rate_limited')
                                ->label('Límite excedido')
                                ->boolean(),
                        ]),
                        
                        KeyValueEntry::make('meta')
                            ->label('Metadatos Adicionales')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadAutoEmailLogs::route('/'),
            'view'  => ViewLeadAutoEmailLog::route('/{record}'),
        ];
    }
}