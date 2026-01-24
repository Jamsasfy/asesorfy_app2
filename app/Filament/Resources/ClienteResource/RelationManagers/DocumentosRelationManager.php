<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Schemas\Schema;
use App\Models\DocumentoSubtipo;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Illuminate\Support\Facades\DB;
use App\Enums\DocumentoEstadoEnum;
use App\Models\Documento;
use Livewire\Component;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\HtmlString;
use Filament\Actions\Action;

use Filament\Notifications\Notification;




class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentosPolimorficos';
    protected static ?string $title = 'Documentos';

    public function isReadOnly(): bool
    {
        return false;
    }

    /* ===========================
     * FORM
     * =========================== */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->relationship('tipo', 'nombre')
                ->required()
                ->live(),

            Select::make('subtipo_documento_id')
                ->label('Subtipo')
                ->options(fn (callable $get) =>
                    DocumentoSubtipo::where('documento_categoria_id', $get('tipo_documento_id'))
                        ->pluck('nombre', 'id')
                )
                ->required()
                ->searchable()
                ->reactive(),

            FileUpload::make('ruta')
                ->label('Archivo')
                ->disk('public')
                ->directory('documentos')
                ->maxSize(32768)
                ->required()
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->visibility('public'),

            TextInput::make('nombre')
                ->label('Nombre del documento')
                ->maxLength(255)
                ->placeholder('Opcional. Si lo dejas vacío se generará automáticamente.')
                ->helperText('Si no se indica, se generará un nombre automático.'),

            // ✅ Observaciones del cliente (solo lectura en admin)
                    Textarea::make('observaciones')
                ->label('Observaciones del cliente')
                ->helperText('Solo lectura. Texto aportado por el cliente en el portal.')
                ->columnSpanFull()
                ->disabled()
                ->visible(fn (string $context) => $context === 'edit'),

            // ✅ Observaciones internas (equipo)
            Textarea::make('observaciones_internas')
                ->label('Observaciones internas')
                ->helperText('Solo visible para el equipo. El cliente NO lo verá.')
                ->columnSpanFull(),
        ]);
    }

    /* ===========================
     * TABLE
     * =========================== */
    public function table(Table $table): Table
    {
       return $table
            ->recordTitleAttribute('nombre')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
    // ✅ Siempre incluye PK y columnas
    $query->select('documentos.*');

    // ✅ Flag duplicado (solo si NO está resuelto)
    // CAST a UNSIGNED para evitar strings raros
    $query->addSelect(DB::raw("
        CAST(
            (
              documentos.file_sha256 IS NOT NULL
              AND documentos.file_sha256 != ''
              AND documentos.duplicate_status IS NULL
              AND EXISTS(
                  SELECT 1
                  FROM documentos d2
                  WHERE d2.cliente_id = documentos.cliente_id
                    AND d2.file_sha256 IS NOT NULL
                    AND d2.file_sha256 != ''
                    AND d2.file_sha256 = documentos.file_sha256
                    AND (
                          d2.created_at < documentos.created_at
                       OR (d2.created_at = documentos.created_at AND d2.id < documentos.id)
                    )
              )
            ) AS UNSIGNED
        ) AS is_duplicate
    "));
})

            ->columns([
                // 🔁 Subido por: Cliente vs AsesorFy (igual idea que en portal)
            IconColumn::make('user_id')
            ->label('Subido por')
            ->icon(function ($state, $record, $livewire) {
                static $portalUserIds = null;

                if ($portalUserIds === null) {
                    $clienteId = $livewire->getOwnerRecord()->id;

                    $portalUserIds = DB::table('cliente_user')
                        ->where('cliente_id', $clienteId)
                        ->pluck('user_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }

                $isCliente = in_array((int) $state, $portalUserIds, true);

                return $isCliente
                    ? 'heroicon-o-arrow-up-right'     // Cliente
                    : 'heroicon-o-arrow-down-left';   // AsesorFy
            })
            ->color(function ($state, $record, $livewire) {
                static $portalUserIds = null;

                if ($portalUserIds === null) {
                    $clienteId = $livewire->getOwnerRecord()->id;

                    $portalUserIds = DB::table('cliente_user')
                        ->where('cliente_id', $clienteId)
                        ->pluck('user_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }

                $isCliente = in_array((int) $state, $portalUserIds, true);

                return $isCliente ? 'success' : 'primary';
            })
            ->tooltip(function ($state, $record, $livewire) {
                static $portalUserIds = null;

                if ($portalUserIds === null) {
                    $clienteId = $livewire->getOwnerRecord()->id;

                    $portalUserIds = DB::table('cliente_user')
                        ->where('cliente_id', $clienteId)
                        ->pluck('user_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }

                $isCliente = in_array((int) $state, $portalUserIds, true);

                return $isCliente ? 'Subido por el cliente' : 'Subido por AsesorFy';
            })
            ->alignCenter(),


                // 📎 Tipo archivo (icono por mime)
                IconColumn::make('mime_type')
                    ->label('')
                    ->icon(function ($record) {
                        $mime = (string) ($record->mime_type ?? '');
                        $ext = strtolower(pathinfo((string) ($record->ruta ?? ''), PATHINFO_EXTENSION));

                        if ($mime === 'application/pdf' || $ext === 'pdf') return 'icon-pdf';

                        if (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                            return 'heroicon-o-photo';
                        }

                        if (in_array($mime, [
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ], true) || in_array($ext, ['doc', 'docx'], true)) return 'icon-doc';

                        if (in_array($mime, [
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ], true) || in_array($ext, ['xls', 'xlsx'], true)) return 'icon-excel';

                        return 'heroicon-o-question-mark-circle';
                    })
                    ->tooltip(fn ($record) => $record->mime_type ?? 'Desconocido')
                    ->color(function ($record) {
                        $mime = (string) ($record->mime_type ?? '');

                        if (str_starts_with($mime, 'image/')) return 'info';
                        if ($mime === 'application/pdf') return 'danger';

                        if (in_array($mime, [
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ], true)) return 'primary';

                        if (in_array($mime, [
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ], true)) return 'success';

                        return 'gray';
                    })
                    ->alignCenter(),

                TextColumn::make('tipo.nombre')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('subtipo.nombre')->label('Subtipo')->badge()->color('gray'),

                // ✅ Estado (badge con colores del Enum)
                TextColumn::make('estado')
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
                    }),      

                // ✅ Icono del estado (igual que portal, con “cliente respondió”)
IconColumn::make('estado_icon')
    ->label('')
    ->state(fn ($record) => $record) // 👈 pasamos el record entero
    ->icon(function ($record) {
        $estado = $record->estado instanceof DocumentoEstadoEnum
            ? $record->estado
            : DocumentoEstadoEnum::tryFrom((string) $record->estado);

        return match ($estado) {
            DocumentoEstadoEnum::VERIFICADO          => 'heroicon-m-check-circle',
            DocumentoEstadoEnum::RECHAZADO           => 'heroicon-m-x-circle',
            DocumentoEstadoEnum::NECESITA_ACLARACION => 'heroicon-m-chat-bubble-left-ellipsis',
            DocumentoEstadoEnum::ARCHIVADO           => 'heroicon-m-archive-box',
            DocumentoEstadoEnum::PENDIENTE           => filled($record->aclaracion_respondida_at)
                ? 'heroicon-m-arrow-path'     // ✅ volvió a revisión tras respuesta
                : 'heroicon-m-exclamation-triangle',
            default                                  => 'heroicon-m-question-mark-circle',
        };
    })
    ->color(function ($record) {
        $estado = $record->estado instanceof DocumentoEstadoEnum
            ? $record->estado
            : DocumentoEstadoEnum::tryFrom((string) $record->estado);

        return match ($estado) {
            DocumentoEstadoEnum::VERIFICADO          => 'success',
            DocumentoEstadoEnum::RECHAZADO           => 'danger',
            DocumentoEstadoEnum::NECESITA_ACLARACION => 'info',
            DocumentoEstadoEnum::ARCHIVADO           => 'gray',
            DocumentoEstadoEnum::PENDIENTE           => filled($record->aclaracion_respondida_at)
                ? 'info'       // ✅ azul/info para “cliente ha contestado”
                : 'warning',   // amarillo para pendiente normal
            default                                  => 'gray',
        };
    })
    ->tooltip(function ($record) {
        $estado = $record->estado instanceof DocumentoEstadoEnum
            ? $record->estado
            : DocumentoEstadoEnum::tryFrom((string) $record->estado);

        if ($estado === DocumentoEstadoEnum::PENDIENTE && filled($record->aclaracion_respondida_at)) {
            return 'Cliente ha contestado la aclaración. Está de nuevo en revisión.';
        }

        return $estado?->label() ?? 'Pendiente';
    })
    ->alignCenter(),


                // ✅ Archivo: link si existe, si no -> "Purgado"
                TextColumn::make('archivo')
                    ->label('Archivo')
                    ->state(fn ($record) => $record->ruta ? $record->nombre : 'Purgado')
                    ->badge(fn ($record) => empty($record->ruta))
                    ->color(fn ($record) => empty($record->ruta) ? 'gray' : null)
                    ->url(fn ($record) => filled($record->ruta) ? Storage::url($record->ruta) : null, true)
                    ->openUrlInNewTab(),


                TextColumn::make('created_at')->label('Subido el')->dateTime('d/m/Y H:i'),
                TextColumn::make('updated_at')->label('Actualizado el')->dateTime('d/m/Y H:i'),
            ])

                ->filters([
                

            // ✅ Tipo + Subtipo REACTIVO (Subtipo aparece solo si hay Tipo)
            Filter::make('tipo_y_subtipo')
                ->label('Tipo / Subtipo')
                ->form([
                    Select::make('tipo_documento_id')
                        ->label('Tipo')
                        ->relationship('tipo', 'nombre')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live(),

                    Select::make('subtipo_documento_id')
                        ->label('Subtipo')
                        ->options(fn (callable $get) => filled($get('tipo_documento_id'))
                            ? DocumentoSubtipo::query()
                                ->where('documento_categoria_id', $get('tipo_documento_id'))
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                                ->toArray()
                            : []
                        )
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (callable $get) => filled($get('tipo_documento_id'))),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (filled($data['tipo_documento_id'] ?? null)) {
                        $query->where('tipo_documento_id', $data['tipo_documento_id']);
                    }

                    if (filled($data['subtipo_documento_id'] ?? null)) {
                        $query->where('subtipo_documento_id', $data['subtipo_documento_id']);
                    }

                    return $query;
                }),

            // ✅ Estado (Enum)
            \Filament\Tables\Filters\SelectFilter::make('estado')
                ->label('Estado')
                ->options([
                    DocumentoEstadoEnum::PENDIENTE->value           => DocumentoEstadoEnum::PENDIENTE->label(),
                    DocumentoEstadoEnum::VERIFICADO->value          => DocumentoEstadoEnum::VERIFICADO->label(),
                    DocumentoEstadoEnum::NECESITA_ACLARACION->value => DocumentoEstadoEnum::NECESITA_ACLARACION->label(),
                    DocumentoEstadoEnum::RECHAZADO->value           => DocumentoEstadoEnum::RECHAZADO->label(),
                    DocumentoEstadoEnum::ARCHIVADO->value           => DocumentoEstadoEnum::ARCHIVADO->label(),
                ])
                ->native(false),


            // ✅ Purgado (ruta NULL)
            \Filament\Tables\Filters\TernaryFilter::make('purgado')
                ->label('Purgado')
                ->placeholder('Todos')
                ->trueLabel('Solo purgados')
                ->falseLabel('Solo con archivo')
                ->queries(
                    true: fn (Builder $query) => $query->whereNull('ruta'),
                    false: fn (Builder $query) => $query->whereNotNull('ruta'),
                    blank: fn (Builder $query) => $query,
                ),

            \Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter::make('created_at')
                ->label('Subido en'),
        ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
        //->filtersFormHeading(null)

                    ->headerActions([
                        CreateAction::make()
                            ->mutateDataUsing(function (array $data): array {
                                $user = Auth::user();
                                $cliente = $this->getOwnerRecord();

                                $data['user_id'] = $user->id;
                                $data['cliente_id'] = $cliente->id;
                                $data['mime_type'] = Storage::disk('public')->mimeType($data['ruta']);
                                $data['verificado'] = (bool) $user->trabajador;

                                // ✅ GENERAR NOMBRE SI NO SE HA ESCRITO
                                if (empty($data['nombre'])) {
                                    $tipo = Str::slug(
                                        optional($cliente->documentosPolimorficos()
                                            ->getModel()
                                            ->tipo()
                                            ->find($data['tipo_documento_id'])
                                        )?->nombre ?? 'tipo'
                                    );

                                    $subtipo = Str::slug(
                                        optional(DocumentoSubtipo::find($data['subtipo_documento_id']))?->nombre ?? 'subtipo'
                                    );

                                    $random = Str::lower(Str::random(6));
                                    $extension = pathinfo($data['ruta'], PATHINFO_EXTENSION);

                                    $data['nombre'] = "{$tipo}_{$subtipo}_{$random}.{$extension}";
                                }

                                return $data;
                            }),
                    ])
                    ->recordActions([
                            //inicio action ver duplicados


                            // perro

                        Action::make('ver_duplicados')
                        ->label('Posible duplicado')
                        ->icon('heroicon-m-document-duplicate')
                        ->color('danger')
                        ->tooltip('Posible duplicado. Pulsa para comparar.')
                        ->visible(fn (Documento $record) => (int) ($record->is_duplicate ?? 0) === 1)
                        // Evitamos cerrar por error, obligando a usar los botones
                        ->closeModalByClickingAway(false)
                        ->closeModalByEscaping(false)
                        ->modalHeading('Comparar posibles duplicados')
                        ->modalWidth('7xl')
                        // Ocultamos el botón "Submit" por defecto ya que usaremos los custom
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')

                        // ✅ BOTONES FOOTER
                        ->extraModalFooterActions([
                            Action::make('no_es_duplicado')
                                ->label('Este NO es duplicado')
                                ->icon('heroicon-o-check')
                                ->color('gray')
                                // Esta línea asegura que tras ejecutar, se cierre el modal padre
                                ->cancelParentActions() 
                                ->action(function (Documento $record, \Filament\Actions\Action $action) {
                                    $record->forceFill([
                                        'duplicate_status'        => 'ignored',
                                        'duplicate_of_id'         => null,
                                        'duplicate_checked_at'    => now(),
                                        'duplicate_checked_by_id' => auth()->id(),
                                    ])->save();

                                    Notification::make()
                                        ->title('Marcado: “Este NO es duplicado”')
                                        ->success()
                                        ->send();
                                    
                                    // Solo refrescamos la tabla, Filament cerrará el modal automáticamente
                                    // al terminar esta función gracias a cancelParentActions()
                                    $action->getLivewire()->dispatch('$refresh');
                                }),

                            Action::make('rechazar_como_duplicado')
                                ->label('Rechazar ESTE como duplicado')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Rechazar ESTE documento como duplicado')
                                ->modalDescription(fn (Documento $record) => "Vas a rechazar el documento #{$record->id} (el que estás revisando ahora).")
                                // Aseguramos cierre total tras confirmar
                                ->cancelParentActions()
                                ->action(function (Documento $record, \Filament\Actions\Action $action) {
                                    $original = Documento::query()
                                        ->where('cliente_id', $record->cliente_id)
                                        ->whereNotNull('file_sha256')
                                        ->where('file_sha256', $record->file_sha256)
                                        ->whereKeyNot($record->id)
                                        ->orderBy('created_at')
                                        ->orderBy('id')
                                        ->first(['id', 'nombre', 'created_at']);

                                    if (! $original) {
                                        Notification::make()
                                            ->title('No se pudo determinar el original')
                                            ->danger()
                                            ->send();
                                        
                                        // Si fallamos, usamos halt() para NO cerrar el modal y que el usuario vea el error
                                        $action->halt();
                                    }

                                    $fecha  = optional($original->created_at)->format('d/m/Y H:i') ?? '—';
                                    $nombre = (string) ($original->nombre ?? '');
                                    $nombre = $nombre !== '' ? Str::limit($nombre, 80) : "Documento #{$original->id}";

                                    $record->forceFill([
                                        'duplicate_status'        => 'confirmed',
                                        'duplicate_of_id'         => $original->id,
                                        'duplicate_checked_at'    => now(),
                                        'duplicate_checked_by_id' => auth()->id(),
                                        'motivo_rechazo'          => "Duplicado del documento #{$original->id} ({$nombre} · {$fecha})",
                                        'estado'                  => \App\Enums\DocumentoEstadoEnum::RECHAZADO,
                                    ])->save();

                                    Notification::make()
                                        ->title('Documento rechazado como duplicado')
                                        ->success()
                                        ->send();

                                    $action->getLivewire()->dispatch('$refresh');
                                }),
                        ])

    ->modalContent(function (Documento $record) {
        $grupo = Documento::query()
            ->where('cliente_id', $record->cliente_id)
            ->whereNotNull('file_sha256')
            ->where('file_sha256', $record->file_sha256)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'nombre', 'estado', 'created_at', 'ruta', 'mime_type']);

        if ($grupo->count() < 2) {
            return new HtmlString('<div class="text-sm text-gray-600">No hay suficientes candidatos para comparar.</div>');
        }

        // “Original” = el más antiguo distinto del actual
        $original = $grupo->firstWhere('id', '!=', $record->id) ?? $grupo->first();
        $actual = $record;

        $renderBadgeEstado = function ($doc) {
            $label = $doc->estado?->label() ?? (string) $doc->estado;
            $color = $doc->estado?->color() ?? 'gray';

            $map = [
                'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/30 dark:text-emerald-200',
                'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/30 dark:text-amber-200',
                'info'    => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-950/30 dark:text-sky-200',
                'danger'  => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/30 dark:text-red-200',
                'gray'    => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-200',
            ];

            $cls = $map[$color] ?? $map['gray'];

            return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$cls}\">{$label}</span>";
        };

        $renderPreview = function ($doc) {
            if (blank($doc->ruta)) {
                return '<div class="flex h-[420px] items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">Archivo no disponible (purgado)</div>';
            }

            $url  = Storage::disk('public')->url($doc->ruta);
            $mime = (string) ($doc->mime_type ?? '');
            $ext  = strtolower(pathinfo((string) $doc->ruta, PATHINFO_EXTENSION));

            if (str_starts_with($mime, 'image/') || in_array($ext, ['png','jpg','jpeg','webp','gif'], true)) {
                return <<<HTML
<div class="rounded-xl border border-gray-200 bg-black/90 p-2 dark:border-white/10">
  <img src="{$url}" class="block h-[420px] w-full rounded-lg object-contain" />
</div>
HTML;
            }

            if ($mime === 'application/pdf' || $ext === 'pdf') {
                $src = $url . '#page=1&zoom=95';
                return <<<HTML
<div class="rounded-xl border border-gray-200 bg-black/90 p-2 dark:border-white/10">
  <iframe src="{$src}" class="h-[420px] w-full rounded-lg" loading="lazy"></iframe>
</div>
HTML;
            }

            $nombre = e(Str::limit((string) ($doc->nombre ?? 'Archivo'), 60));
            $ruta   = e((string) $doc->ruta);

            return <<<HTML
<div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
  <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{$nombre}</div>
  <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sin vista previa. Usa “Ver”.</div>
  <div class="mt-3 break-all text-[11px] text-gray-500 dark:text-gray-400">{$ruta}</div>
</div>
HTML;
        };

        $card = function ($doc, string $tagHtml, string $subtitle, bool $highlight = false) use ($renderBadgeEstado, $renderPreview) {
            $id = (int) $doc->id;
            $nombre = e(Str::limit((string) ($doc->nombre ?? "Documento #{$id}"), 70));
            $fecha = optional($doc->created_at)->format('d/m/Y H:i') ?? '—';

            $estado  = $renderBadgeEstado($doc);
            $preview = $renderPreview($doc);

            $ring = $highlight ? 'ring-2 ring-red-500/60 dark:ring-red-400/40' : '';

            return <<<HTML
<div class="space-y-3 {$ring} rounded-2xl p-2">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">#{$id} · {$nombre}</div>
        {$tagHtml}
        {$estado}
      </div>
      <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{$subtitle} · {$fecha}</div>
    </div>
  </div>
  {$preview}
</div>
HTML;
        };

        $tagEste = '<span class="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-950/30 dark:text-red-200">ESTE (POSIBLE DUPLICADO)</span>';
        $tagOriginal = '<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-950/30 dark:text-blue-200">Original</span>';

        $banner = <<<HTML
<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/20 dark:bg-red-950/30 dark:text-red-200">
  ⚠️ <b>Las acciones de abajo se aplican al documento #{$actual->id}</b> (el “posible duplicado nuevo”).
</div>
HTML;

        // IZQ = actual, DER = original
        $htmlIzq = $card($actual, $tagEste, 'Documento que estás revisando', true);
        $htmlDer = $card($original, $tagOriginal, 'Documento base (más antiguo)');

        return new HtmlString(<<<HTML
{$banner}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div>{$htmlIzq}</div>
  <div>{$htmlDer}</div>
</div>
HTML);
    }),


    //fin Action ver duplicados
                    ViewAction::make()
                            ->label('Ver')
                            ->url(fn ($record) =>
                                route('filament.admin.resources.documentos.view', ['record' => $record->id])
                                . '?chain=1&cliente=' . $this->getOwnerRecord()->getKey()
                            )
                            ->openUrlInNewTab(),


                        EditAction::make(),
                        DeleteAction::make(),
                    ])
                    ->toolbarActions([
                        BulkActionGroup::make([
                            DeleteBulkAction::make(),
                        ]),
                    ]);
    }
}
