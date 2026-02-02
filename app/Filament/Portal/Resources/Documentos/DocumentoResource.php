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

    protected static ?string $navigationLabel = 'Documentos';
    protected static ?string $modelLabel = 'Documento';
    protected static ?string $pluralModelLabel = 'Documentos';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestión';


    public static function getNavigationLabel(): string
    {
        return 'Documentos';
    }

    public static function getNavigationBadge(): ?string
            {
                $user = auth()->user();
                if (! $user) return null;

                $clienteIds = $user->clientes()->pluck('clientes.id');

                // ✅ Pendientes de respuesta del cliente:
                // estado NECESITA_ACLARACION + aún NO ha contestado
                $count = Documento::query()
                    ->whereIn('cliente_id', $clienteIds)
                    ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION->value)
                    ->whereNull('aclaracion_respondida_at')
                    ->count();

                return $count > 0 ? (string) $count : null;
            }

            public static function getNavigationBadgeColor(): ?string
            {
                return 'warning';
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
            // =========================
            // INFO PRINCIPAL
            // =========================
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
                            $enum = $state instanceof DocumentoEstadoEnum
                                ? $state
                                : DocumentoEstadoEnum::tryFrom((string) $state);

                            return match ($enum) {
                                DocumentoEstadoEnum::VERIFICADO          => 'Verificado',
                                DocumentoEstadoEnum::PENDIENTE           => 'En revisión',
                                DocumentoEstadoEnum::NECESITA_ACLARACION => 'Requiere tu respuesta',
                                DocumentoEstadoEnum::RECHAZADO           => 'Rechazado',
                                DocumentoEstadoEnum::ARCHIVADO           => 'Archivado',
                                default                                  => 'En revisión',
                            };
                        })
                        ->color(function ($state) {
                            $enum = $state instanceof DocumentoEstadoEnum
                                ? $state
                                : DocumentoEstadoEnum::tryFrom((string) $state);

                            return match ($enum) {
                                DocumentoEstadoEnum::VERIFICADO          => 'success',
                                DocumentoEstadoEnum::PENDIENTE           => 'gray',
                                DocumentoEstadoEnum::NECESITA_ACLARACION => 'warning',
                                DocumentoEstadoEnum::RECHAZADO           => 'danger',
                                DocumentoEstadoEnum::ARCHIVADO           => 'gray',
                                default                                  => 'gray',
                            };
                        })
                        ->columnSpan(1),

                    // ✅ Icono del estado separado
                    IconEntry::make('estado_icon')
                        ->hiddenLabel()
                        ->state(fn ($record) => $record->estado)
                        ->icon(function ($state) {
                            $enum = $state instanceof DocumentoEstadoEnum
                                ? $state
                                : DocumentoEstadoEnum::tryFrom((string) $state);

                            return match ($enum) {
                                DocumentoEstadoEnum::VERIFICADO          => 'heroicon-m-check-circle',
                                DocumentoEstadoEnum::PENDIENTE           => 'heroicon-m-clock',
                                DocumentoEstadoEnum::NECESITA_ACLARACION => 'heroicon-m-question-mark-circle',
                                DocumentoEstadoEnum::RECHAZADO           => 'heroicon-m-x-circle',
                                DocumentoEstadoEnum::ARCHIVADO           => 'heroicon-m-archive-box',
                                default                                  => 'heroicon-m-question-mark-circle',
                            };
                        })
                        ->color(function ($state) {
                            $enum = $state instanceof DocumentoEstadoEnum
                                ? $state
                                : DocumentoEstadoEnum::tryFrom((string) $state);

                            return match ($enum) {
                                DocumentoEstadoEnum::VERIFICADO          => 'success',
                                DocumentoEstadoEnum::PENDIENTE           => 'gray',
                                DocumentoEstadoEnum::NECESITA_ACLARACION => 'warning',
                                DocumentoEstadoEnum::RECHAZADO           => 'danger',
                                DocumentoEstadoEnum::ARCHIVADO           => 'gray',
                                default                                  => 'gray',
                            };
                        })
                        ->tooltip(function ($state) {
                            $enum = $state instanceof DocumentoEstadoEnum
                                ? $state
                                : DocumentoEstadoEnum::tryFrom((string) $state);

                            return $enum?->label() ?? 'Pendiente';
                        })
                        ->columnSpan(1),

                    TextEntry::make('created_at')
                        ->label('🕓 Subido el')
                        ->dateTime('d/m/Y H:i')
                        ->columnSpan(1),

                    TextEntry::make('revisado_at')
                        ->label('✅ Revisado el')
                        ->dateTime('d/m/Y H:i')
                        ->visible(function ($record) {
                            $estado = $record->estado instanceof DocumentoEstadoEnum
                                ? $record->estado
                                : DocumentoEstadoEnum::tryFrom((string) $record->estado);

                            return in_array($estado, [
                                DocumentoEstadoEnum::VERIFICADO,
                                DocumentoEstadoEnum::RECHAZADO,
                            ], true);
                        })
                        ->columnSpan(1),

                    TextEntry::make('revisadoPor.name')
                        ->label('👤 Revisado por')
                        ->placeholder('—')
                        ->visible(function ($record) {
                            $estado = $record->estado instanceof DocumentoEstadoEnum
                                ? $record->estado
                                : DocumentoEstadoEnum::tryFrom((string) $record->estado);

                            return in_array($estado, [
                                DocumentoEstadoEnum::VERIFICADO,
                                DocumentoEstadoEnum::RECHAZADO,
                            ], true);
                        })
                        ->columnSpan(1),
                ])
                ->columns(7),

            // =========================
            // SECCIÓN: NECESITA ACLARACIÓN (solo si NO ha respondido)
            // =========================
            Section::make()
                ->schema([
                    TextEntry::make('aclaracion_title')
                        ->hiddenLabel()
                        ->state('Ayúdanos con una aclaración 😊')
                        ->weight(FontWeight::ExtraBold)
                        ->size(TextSize::Large)
                        ->color('warning'),

                    TextEntry::make('aclaracion_subtitle')
                        ->hiddenLabel()
                        ->state('Respóndenos esto y lo validamos enseguida. Si no recibimos respuesta, no podremos validar tu documento.')
                        ->size(\Filament\Support\Enums\TextSize::Small)
                        ->weight(FontWeight::Bold)
                        ->color('gray'),

                    TextEntry::make('aclaracion_pregunta')
                        ->hiddenLabel()
                        ->state(function ($record) {
                            $text = e((string) ($record->aclaracion_pregunta ?? '—'));

                            $tpl = <<<'HTML'
<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
            <span class="text-2xl font-black text-sky-700 dark:text-sky-200">?</span>
        </div>

        <div class="min-w-0">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Tu asesor te pregunta</div>
            <div class="mt-1 whitespace-pre-line text-sm font-semibold text-gray-900 dark:text-gray-100">%s</div>
        </div>
    </div>
</div>
HTML;

                            return new \Illuminate\Support\HtmlString(sprintf($tpl, $text));
                        })
                        ->html()
                        ->columnSpanFull(),

                    TextEntry::make('aclaracion_actions')
                        ->hiddenLabel()
                        ->state(fn () => '')
                        ->belowContent([
                            Action::make('responder_aclaracion')
                                ->label('Responder')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('warning')
                                ->button()
                                ->size('lg')

                                // ✅ Evitar cierres accidentales
                                ->closeModalByClickingAway(false)
                                ->closeModalByEscaping(false)

                                // ✅ Personalizar el botón de enviar del modal
                                ->modalSubmitActionLabel('Enviar respuesta')
                                ->modalSubmitAction(fn (\Filament\Actions\Action $action) => $action
                                    ->label('Enviar respuesta')
                                    ->color('warning')
                                    ->icon('heroicon-o-paper-airplane')
                                    // ✅ deshabilita el submit mientras ejecuta
                                    ->extraAttributes([
                                        'wire:loading.attr' => 'disabled',
                                        'wire:target' => 'mountedActions',
                                    ])
                                )

                                // ✅ Mensaje visible mientras se guarda (sin JS)
                                ->modalContent(new \Illuminate\Support\HtmlString(<<<HTML
                                    <div wire:loading wire:target="mountedActions"
                                        class="mb-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-950/40 dark:text-warning-200">
                                        ⏳ Enviando tu respuesta… espera un momento, por favor.
                                    </div>
                                HTML))

                                ->modalHeading('Responder aclaración')
                                ->modalDescription('Tu respuesta se enviará al asesor para que pueda validar el documento.')

                                ->form([
                                    \Filament\Forms\Components\Textarea::make('aclaracion_respuesta')
                                        ->label('Tu respuesta')
                                        ->required()
                                        ->rows(4)
                                        ->helperText('Sé breve y directo 😊'),
                                ])

                                ->action(function ($record, array $data, $livewire) {
                                    $record->aclaracion_respuesta = $data['aclaracion_respuesta'] ?? null;
                                    $record->aclaracion_respondida_at = now();

                                    $record->estado = \App\Enums\DocumentoEstadoEnum::PENDIENTE->value;
                                    $record->revisado_at = null;
                                    $record->revisado_por_id = null;

                                    $record->save();

                                    \Filament\Notifications\Notification::make()
                                        ->title('✅ Respuesta enviada')
                                        ->body('¡Gracias! Tu asesor revisará el documento en cuanto pueda.')
                                        ->success()
                                        ->send();

                                    $url = \App\Filament\Portal\Resources\Documentos\DocumentoResource::getUrl('index') . '?tab=requiere_atencion';

                                    return $livewire->redirect($url, navigate: true);
                                }),


                        ]),
                ])
                ->columns(1)
                ->visible(function ($record) {
                    $estado = $record->estado instanceof DocumentoEstadoEnum
                        ? $record->estado
                        : DocumentoEstadoEnum::tryFrom((string) $record->estado);

                    return $estado === DocumentoEstadoEnum::NECESITA_ACLARACION
                        && blank($record->aclaracion_respondida_at);
                }),

            // =========================
            // SECCIÓN: RESPUESTA ENVIADA (cuando ya respondió)
            // =========================
            Section::make()
    ->schema([
        TextEntry::make('respuesta_title')
            ->hiddenLabel()
            ->state('✅ Respuesta enviada a la pregunta de tu asesor')
            ->weight(\Filament\Support\Enums\FontWeight::ExtraBold)
            ->color('success'),

        TextEntry::make('chat_resumen')
            ->hiddenLabel()
            ->state(function ($record) {
                $pregunta = trim((string) ($record->aclaracion_pregunta ?? ''));
                $respuesta = trim((string) ($record->aclaracion_respuesta ?? ''));

                $preguntaEsc  = e($pregunta !== '' ? $pregunta : '—');
                $respuestaEsc = e($respuesta !== '' ? $respuesta : '—');

                // ✅ Iniciales del cliente (usuario portal)
                $userName = (string) (auth()->user()?->name ?? 'Tú');
                $parts = preg_split('/\s+/', trim($userName)) ?: [];
                $ini = strtoupper(mb_substr($parts[0] ?? 'T', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
                if ($ini === '' || $ini === 'T') {
                    $ini = 'T';
                }

                // ✅ Fechas
              $dtPregunta = '—';
                if (! blank($record->aclaracion_at)) {
                    try {
                        $dtPregunta = \Illuminate\Support\Carbon::parse($record->aclaracion_at)->format('d/m/Y H:i');
                    } catch (\Throwable $e) {
                        $dtPregunta = '—';
                    }
                }

                $dtRespuesta = '—';
                if (! blank($record->aclaracion_respondida_at)) {
                    try {
                        $dtRespuesta = \Illuminate\Support\Carbon::parse($record->aclaracion_respondida_at)->format('d/m/Y H:i');
                    } catch (\Throwable $e) {
                        $dtRespuesta = '—';
                    }
                }


                return new \Illuminate\Support\HtmlString(<<<HTML
<div class="space-y-3">
    <!-- Asesor -->
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
            <span class="text-sm font-extrabold text-sky-800 dark:text-sky-200">AF</span>
        </div>

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Asesor AsesorFy</div>
                <div class="text-[11px] text-gray-400 dark:text-gray-500">·</div>
                <div class="text-[11px] text-gray-400 dark:text-gray-500">{$dtPregunta}</div>
            </div>

            <div class="mt-1 rounded-2xl rounded-tl-sm border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-100">
                <div class="whitespace-pre-line font-semibold">{$preguntaEsc}</div>
            </div>
        </div>
    </div>

    <!-- Cliente -->
    <div class="flex items-start justify-end gap-3">
        <div class="min-w-0 text-right">
            <div class="flex flex-wrap items-center justify-end gap-x-2 gap-y-0.5">
                <div class="text-[11px] text-gray-400 dark:text-gray-500">{$dtRespuesta}</div>
                <div class="text-[11px] text-gray-400 dark:text-gray-500">·</div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Tú</div>
            </div>

            <div class="mt-1 inline-block rounded-2xl rounded-tr-sm border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-400/20 dark:bg-warning-950/40 dark:text-warning-100">
                <div class="whitespace-pre-line font-semibold">{$respuestaEsc}</div>
            </div>
        </div>

        <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-warning-50 ring-1 ring-warning-200 dark:bg-warning-950/40 dark:ring-warning-400/20">
            <span class="text-sm font-extrabold text-warning-800 dark:text-warning-200">{$ini}</span>
        </div>
    </div>
</div>
HTML);
            })
            ->html()
            ->columnSpanFull(),
    ])
    ->columns(1)
    ->visible(fn ($record) => filled($record->aclaracion_respuesta) && filled($record->aclaracion_respondida_at)),




            // =========================
            // RECHAZADO
            // =========================
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
                    $estado = $record->estado instanceof DocumentoEstadoEnum
                        ? $record->estado
                        : DocumentoEstadoEnum::tryFrom((string) $record->estado);

                    return $estado === DocumentoEstadoEnum::RECHAZADO;
                }),

            // =========================
            // OBSERVACIONES (solo si no rechazado)
            // =========================
            Section::make('Observaciones')
                ->schema([
                    TextEntry::make('observaciones')
                        ->label('📝 Mis observaciones')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->visible(function ($record) {
                    $estado = $record->estado instanceof DocumentoEstadoEnum
                        ? $record->estado
                        : DocumentoEstadoEnum::tryFrom((string) $record->estado);

                    $isRejected = $estado === DocumentoEstadoEnum::RECHAZADO;

                    return ! $isRejected && filled($record->observaciones);
                }),

            // =========================
            // ARCHIVO
            // =========================
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
