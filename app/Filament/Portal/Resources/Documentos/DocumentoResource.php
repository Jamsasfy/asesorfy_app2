<?php

namespace App\Filament\Portal\Resources\Documentos;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Portal\Resources\Documentos\Pages\CreateDocumento;
use App\Filament\Portal\Resources\Documentos\Pages\EditDocumento;
use App\Filament\Portal\Resources\Documentos\Pages\ListDocumentos;
use App\Filament\Portal\Resources\Documentos\Pages\ViewDocumento;
use App\Filament\Portal\Resources\Documentos\Schemas\DocumentoForm;
use App\Filament\Portal\Resources\Documentos\Tables\DocumentosTable;
use App\Models\Documento;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Joaopaulolndev\FilamentPdfViewer\Infolists\Components\PdfViewerEntry;

class DocumentoResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $navigationLabel = 'Mis Documentos';
    protected static ?string $modelLabel = 'Documento';
    protected static ?string $pluralModelLabel = 'Mis Documentos';

    public static function getNavigationLabel(): string
    {
        return 'Mis Documentos';
    }

    // ✅ Portal sin Shield / sin Policies
    protected static bool $shouldSkipAuthorization = true;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        // usuario portal -> clientes via cliente_user
        $clienteIds = $user->clientes()->pluck('clientes.id')->all();

        return parent::getEloquentQuery()
            ->whereIn('cliente_id', $clienteIds);
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentosTable::configure($table);
    }

    /**
     * Vista del documento (detalle) estilo admin:
     * - botones Ver / Descargar
     * - preview PDF o imagen
     * - observaciones visibles para el cliente
     * - sección RECHAZADO con motivo
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
               Section::make('Información')
    ->schema([
        TextEntry::make('nombre')
            ->label('📄 Documento')
            ->weight(\Filament\Support\Enums\FontWeight::Bold)
            ->columnSpan(2),

        TextEntry::make('tipo.nombre')
            ->label('📁 Tipo')
            ->badge()
            ->color(fn ($record) => $record->tipo?->color ?? 'gray')
            ->columnSpan(1),

        TextEntry::make('subtipo.nombre')
            ->label('📂 Subtipo')
            ->badge()
            ->color('gray')
            ->columnSpan(1),

        // ✅ Estado (badge)
        TextEntry::make('estado')
            ->label('Estado')
            ->badge()
            ->formatStateUsing(function ($state) {
                $enum = $state instanceof \App\Enums\DocumentoEstadoEnum
                    ? $state
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $state);

                return $enum?->label() ?? 'Pendiente';
            })
            ->color(function ($state) {
                $enum = $state instanceof \App\Enums\DocumentoEstadoEnum
                    ? $state
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $state);

                return $enum?->color() ?? 'warning';
            })
            ->columnSpan(1),

        // ✅ Icono del estado separado
        IconEntry::make('estado_icon')
            ->hiddenLabel()
            ->state(fn ($record) => $record->estado)
            ->icon(function ($state) {
                $enum = $state instanceof \App\Enums\DocumentoEstadoEnum
                    ? $state
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $state) ?? \App\Enums\DocumentoEstadoEnum::PENDIENTE;

                return match ($enum) {
                    \App\Enums\DocumentoEstadoEnum::VERIFICADO => 'heroicon-m-check-circle',
                    \App\Enums\DocumentoEstadoEnum::PENDIENTE  => 'heroicon-m-exclamation-triangle',
                    \App\Enums\DocumentoEstadoEnum::RECHAZADO  => 'heroicon-m-x-circle',
                };
            })
            ->color(function ($state) {
                $enum = $state instanceof \App\Enums\DocumentoEstadoEnum
                    ? $state
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $state) ?? \App\Enums\DocumentoEstadoEnum::PENDIENTE;

                return match ($enum) {
                    \App\Enums\DocumentoEstadoEnum::VERIFICADO => 'success',
                    \App\Enums\DocumentoEstadoEnum::PENDIENTE  => 'warning',
                    \App\Enums\DocumentoEstadoEnum::RECHAZADO  => 'danger',
                };
            })
            ->tooltip(function ($state) {
                $enum = $state instanceof \App\Enums\DocumentoEstadoEnum
                    ? $state
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $state);

                return $enum?->label() ?? 'Pendiente';
            })
            ->columnSpan(1),

        // 🕓 Subido el
        TextEntry::make('created_at')
            ->label('🕓 Subido el')
            ->dateTime('d/m/Y H:i')
            ->columnSpan(1),

        // ✅ SOLO si está verificado o rechazado
        TextEntry::make('revisado_at')
            ->label('✅ Revisado el')
            ->dateTime('d/m/Y H:i')
            ->visible(function ($record) {
                $estado = $record->estado instanceof \App\Enums\DocumentoEstadoEnum
                    ? $record->estado
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $record->estado);

                return in_array($estado, [
                    \App\Enums\DocumentoEstadoEnum::VERIFICADO,
                    \App\Enums\DocumentoEstadoEnum::RECHAZADO,
                ], true);
            })
            ->columnSpan(1),

        TextEntry::make('revisadoPor.name')
            ->label('👤 Revisado por')
           
            ->placeholder('—')
            ->visible(function ($record) {
                $estado = $record->estado instanceof \App\Enums\DocumentoEstadoEnum
                    ? $record->estado
                    : \App\Enums\DocumentoEstadoEnum::tryFrom((string) $record->estado);

                return in_array($estado, [
                    \App\Enums\DocumentoEstadoEnum::VERIFICADO,
                    \App\Enums\DocumentoEstadoEnum::RECHAZADO,
                ], true);
            })
            ->columnSpan(1),
    ])
    ->columns(7),


                // ✅ Nueva sección solo si RECHAZADO
                Section::make()
    ->schema([
        TextEntry::make('rechazo_title')
            ->hiddenLabel()
            ->state('⛔ Documento rechazado')
            ->weight(FontWeight::ExtraBold)
            ->size(TextSize::Large)
            ->color('danger'),
        TextEntry::make('revisado_at')
            ->label('Revisado el')
            ->dateTime('d/m/Y H:i')
            ->placeholder('—')
            ->color('danger'),
    

        TextEntry::make('motivo_rechazo')
            ->label('Motivo del rechazo')
            ->placeholder('—')
            ->columnSpanFull()
            ->color('danger'),

      
    ])
    ->columns(1)
    ->visible(function ($record) {
        $estado = $record->estado;

        if ($estado instanceof DocumentoEstadoEnum) {
            return $estado === DocumentoEstadoEnum::RECHAZADO;
        }

        return (string) $estado === 'rechazado';
    }),
                // Observaciones (si NO está rechazado, lo dejamos como antes)
                Section::make('Observaciones')
                    ->schema([
                        TextEntry::make('observaciones')
                            ->label('📝 Mi observaciones')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->visible(function ($record) {
                        $estado = $record->estado;

                        $isRejected = $estado instanceof DocumentoEstadoEnum
                            ? $estado === DocumentoEstadoEnum::RECHAZADO
                            : (string) $estado === 'rechazado';

                        return ! $isRejected && filled($record->observaciones);
                    }),

                Section::make('Archivo')
                    ->schema([
                        TextEntry::make('acciones_archivo')
                            ->label(false)
                            ->state(fn () => '')
                            ->belowContent([
                                Action::make('ver_archivo')
                                    ->label('Ver')
                                    ->icon('heroicon-o-eye')
                                    ->color('info')
                                    ->url(fn ($record) => Storage::disk('public')->url($record->ruta))
                                    ->openUrlInNewTab(),

                                Action::make('descargar_archivo')
                                    ->label('Descargar')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->url(fn ($record) => Storage::disk('public')->url($record->ruta))
                                    ->openUrlInNewTab(),
                                    
                            ])
                            ->visible(fn ($record) => filled($record->ruta)),

                        TextEntry::make('preview_imagen')
                            ->label('Vista previa')
                            ->state(function ($record) {
                                $url = Storage::disk('public')->url($record->ruta);

                                return <<<HTML
                                            <div style="display:flex;justify-content:center;align-items:center;background:#0f0f0f;padding:1rem;border-radius:12px;">
                                                <img src="{$url}" style="max-width:100%;max-height:650px;object-fit:contain;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.4);" />
                                            </div>
                                            HTML;
                            })
                            ->html()
                            ->visible(fn ($record) => str_starts_with((string) $record->mime_type, 'image/')),

                        PdfViewerEntry::make('preview_pdf')
                            ->label('Vista previa')
                            ->fileUrl(fn ($record) => Storage::disk('public')->url($record->ruta))
                            ->minHeight('700px')
                            ->visible(fn ($record) => (string) $record->mime_type === 'application/pdf'),
                    ]),
            ]);
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
            'index' => ListDocumentos::route('/'),
          //  'create' => CreateDocumento::route('/create'),
            'edit' => EditDocumento::route('/{record}/edit'),
            'view' => ViewDocumento::route('/{record}/view'),
        ];
    }
}
