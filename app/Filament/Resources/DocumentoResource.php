<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use App\Models\Proyecto;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Actions\Action;
use Filament\Support\Enums\TextSize;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DocumentoResource\Pages\ListDocumentos;
use App\Filament\Resources\DocumentoResource\Pages\CreateDocumento;
use App\Filament\Resources\DocumentoResource\Pages\EditDocumento;
use App\Filament\Resources\DocumentoResource\Pages\ViewDocumento;
use App\Filament\Resources\DocumentoResource\Pages;
use App\Filament\Resources\DocumentoResource\Widgets\DocumentoStats;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\DocumentoCategoria;
use App\Models\DocumentoSubtipo;
use App\Models\User;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Joaopaulolndev\FilamentPdfViewer\Infolists\Components\PdfViewerEntry;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Illuminate\Support\HtmlString;

use Illuminate\Support\Str; // 👈 ESTA ES LA QUE TE FALTA

use App\Enums\DocumentoEstadoEnum;
use Filament\Forms\Components\Textarea as FormTextarea;

use Filament\Schemas\Components\Actions;





class DocumentoResource extends Resource 
{
    protected static ?string $model = Documento::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-databasesearch-o';

  //  protected static ?string $navigationIcon = 'icon-customer';
    protected static string | \UnitEnum | null $navigationGroup = 'Documentos y BBDD';
    protected static ?string $navigationLabel = 'Documentos de Clientes';
    protected static ?string $modelLabel = 'Documento';
    protected static ?string $pluralModelLabel = 'Todos los Documentos';



   

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('asesor') ? 'Documentos de mis Clientes' : 'Documentos de Clientes';
    }

public static function getNavigationGroup(): ?string
    {
        // Si el usuario es un asesor, asigna el grupo "Mi espacio de trabajo"
        if (auth()->user()?->hasRole('asesor')) {
            return 'Mi espacio de trabajo';

        }

        // Si no es asesor (ej. super_admin), retorna null para que no se agrupe
        // o puedes devolver un nombre de grupo diferente para ellos si lo deseas.
        // Ejemplo: return 'Gestión General';
        return 'Documentos y BBDD';
    }

    public static function getEloquentQuery(): Builder
{
    /** @var User|null $user */
    $user = Auth::user();
    $query = static::getModel()::query(); // Comienza con la consulta del modelo del recurso

    if (!$user) {
        return $query->whereRaw('1 = 0');
    }

    // Descomenta esta línea para depurar quién está accediendo y qué roles tiene:
    // dd('User in DocumentoResource::getEloquentQuery():', $user->email, $user->getRoleNames()->toArray());

    if ($user->hasRole('super_admin')) {
        return $query;
    }

    if ($user->hasRole('asesor')) {
        return $query->whereHas('cliente', function (Builder $subQuery) use ($user) {
            $subQuery->where('asesor_id', $user->id);
        });
    }

    return $query->whereRaw('1 = 0'); // Default: no data for other roles
}



    public static function shouldRegisterNavigation(): bool
{
    /** @var User|null $user */
    $user = auth()->user();

    if (!$user) {
        return false;
    }

    // Permitir si es super_admin
    if ($user->hasRole('super_admin')) {
        return true;
    }

    // Permitir si es asesor Y tiene el permiso para ver cualquier documento
    // (la consulta luego se encargará de filtrar cuáles ve)
    if ($user->hasRole('asesor')) {
        return $user->can('ViewAny:Documento');
    }  
    if ($user->hasRole('coordinador')) {
         return $user->can('ViewAny:Documento');
    } 

    return false; // Por defecto, no mostrar para otros roles
}





   public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->helperText('Selecciona el tipo genérico o familia del documento.')
                ->relationship('tipo', 'nombre')
                ->required()
                ->live(),

            Select::make('subtipo_documento_id')
                ->label('Subtipo')
                ->helperText('Selecciona el subtipo. Si no existe, contacta con tu superior.')
                ->options(fn (callable $get) => DocumentoSubtipo::where('documento_categoria_id', $get('tipo_documento_id'))->pluck('nombre', 'id'))
                ->reactive()
                ->required()
                ->searchable()
                ->placeholder('Selecciona primero el tipo'),

            FileUpload::make('ruta')
                ->label('Archivo')
                ->disk('public')
                ->directory('documentos')
                ->maxSize(32768)
                ->required()
                ->moveFiles()
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
                ->preserveFilenames(false)
                ->visibility('public')
                ->visible(fn (string $context) => $context === 'create')
                ->validationMessages([
                    'required' => 'Por favor, selecciona un archivo.',
                    'accepted_file_types' => 'Solo se permiten PDF, imágenes, Word y Excel.',
                    'max' => 'El archivo excede el tamaño máximo de 32 MB.',
                ])
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $set('nombre', $state->getClientOriginalName());
                    }
                }),

            TextInput::make('nombre')
                ->label('Nombre del documento')
                ->required()
                ->maxLength(255)
                ->placeholder('Se rellenará automáticamente con el nombre del archivo')
                ->helperText('Puedes modificar el nombre si lo deseas.'),

            Textarea::make('observaciones')
                ->label('Observaciones del cliente')
                ->helperText('Solo lectura. Texto aportado por el cliente.')
                ->columnSpanFull()
                    ->visible(fn (string $context) => $context === 'edit')

                ->disabled(),

            Textarea::make('observaciones_internas')
                ->label('Observaciones internas')
                ->helperText('Solo visible para el equipo. El cliente NO lo verá.')
                ->columnSpanFull(),
                

            // ✅ NUEVO: Estado (Enum)
            Select::make('estado')
                ->label('Estado')
                ->options([
                    DocumentoEstadoEnum::PENDIENTE->value  => DocumentoEstadoEnum::PENDIENTE->label(),
                    DocumentoEstadoEnum::VERIFICADO->value => DocumentoEstadoEnum::VERIFICADO->label(),
                    DocumentoEstadoEnum::RECHAZADO->value  => DocumentoEstadoEnum::RECHAZADO->label(),
                ])
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    // compat boolean
                    $set('verificado', $state === DocumentoEstadoEnum::VERIFICADO->value);

                    // si deja de ser rechazado, limpia motivo
                    if ($state !== DocumentoEstadoEnum::RECHAZADO->value) {
                        $set('motivo_rechazo', null);
                    }
                }),

            // ✅ NUEVO: Motivo rechazo (obligatorio si rechazado)
            FormTextarea::make('motivo_rechazo')
                ->label('Motivo de rechazo')
                ->placeholder('Explica por qué se rechaza y qué debe corregir el cliente…')
                ->rows(4)
                ->columnSpanFull()
                ->visible(fn (callable $get) => $get('estado') === DocumentoEstadoEnum::RECHAZADO->value)
                ->required(fn (callable $get) => $get('estado') === DocumentoEstadoEnum::RECHAZADO->value),

            // Selección de cliente
            Select::make('cliente_id')
                ->label('Cliente')
                ->relationship(
                    name: 'cliente',
                    titleAttribute: 'razon_social',
                    modifyQueryUsing: function (Builder $query) {
                        /** @var User|null $user */
                        $user = Auth::user();

                        if ($user) {
                            if ($user->hasRole('super_admin')) {
                                // sin filtro
                            } elseif ($user->hasRole('asesor')) {
                                $query->where('asesor_id', $user->id);
                            }
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    }
                )
                ->searchable()
                ->preload()
                ->required()
                ->reactive(),

            Select::make('documentable_type')
                ->label('¿A qué va asociado?')
                ->options([
                    'App\Models\Cliente' => 'Cliente',
                    'App\Models\Proyecto' => 'Proyecto',
                ])
                ->required()
                ->reactive(),

            Select::make('documentable_id')
                ->label('Selecciona el registro')
                ->required()
                ->searchable()
                ->options(function (callable $get) {
                    $type = $get('documentable_type');
                    $clienteId = $get('cliente_id');

                    if ($type === 'App\Models\Cliente' && $clienteId) {
                        $cliente = Cliente::find($clienteId);
                        return $cliente ? [$cliente->id => $cliente->razon_social] : [];
                    }

                    if ($type === 'App\Models\Proyecto' && $clienteId) {
                        return Proyecto::where('cliente_id', $clienteId)->pluck('nombre', 'id');
                    }

                    return [];
                })
                ->visible(fn (callable $get) => filled($get('documentable_type')))
                ->helperText('Primero selecciona cliente y después el tipo de asociación.'),
        ]);
}


public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
{
    return $schema
        ->columns(12)
        ->components([
            // =========================
            // COLUMNA IZQUIERDA (INFO)
            // =========================
            \Filament\Schemas\Components\Section::make()
                ->columnSpan(4)
                ->schema([

       // =========================
                // POSIBLE DUPLICADO (VISTA)
                // =========================

                \Filament\Schemas\Components\Section::make('Posible Duplicado Detectado')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->iconColor('danger')
                    ->extraAttributes([
                        'class' => 'border-l-4 border-l-red-500 bg-red-50 dark:bg-red-900/20 ring-1 ring-red-900/5 mb-6',
                    ])
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('duplicate_warning')
                            ->hiddenLabel()
                            ->state('Este archivo parece ser una copia de otro ya existente.')
                            ->color('danger')
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),

                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('ver_duplicados_view')
                                ->label('Comparar duplicados')
                                ->icon('heroicon-m-document-duplicate')
                                ->color('danger')
                                ->modalHeading('Comparar posibles duplicados')
                                ->modalWidth('7xl')
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Cerrar')

                                // ✅ Footer actions “compatibles v4” (sin acciones anidadas)
                                ->extraModalFooterActions(fn (\Filament\Actions\Action $action): array => [
                                    $action->makeModalSubmitAction('no_duplicate', arguments: ['decision' => 'ignored'])
                                        ->label('Este NO es duplicado')
                                        ->icon('heroicon-o-check')
                                        ->color('gray'),

                                    $action->makeModalSubmitAction('reject_duplicate', arguments: ['decision' => 'confirmed'])
                                        ->label('Rechazar ESTE como duplicado')
                                        ->icon('heroicon-o-x-circle')
                                        ->color('danger')
                                        ->requiresConfirmation(),
                                ])

                                // ✅ Aquí se ejecutan ambos botones (según $arguments['decision'])
                                ->action(function (\App\Models\Documento $record, array $arguments, \Livewire\Component $livewire) {
                                    $decision = $arguments['decision'] ?? null;

                                    // -----------------------------
                                    // 1) ESTE NO ES DUPLICADO
                                    // -----------------------------
                                    if ($decision === 'ignored') {
                                        $record->update([
                                            'duplicate_status'        => 'ignored',
                                            'duplicate_checked_at'    => now(),
                                            'duplicate_checked_by_id' => auth()->id(),
                                        ]);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Marcado: “Este NO es duplicado”')
                                            ->success()
                                            ->send();

                                        // refresca para que desaparezca el bloque
                                        $record->refresh();
                                        $livewire->dispatch('$refresh');

                                        return;
                                    }

                                    // -----------------------------
                                    // 2) RECHAZAR ESTE COMO DUPLICADO
                                    // -----------------------------
                                    if ($decision === 'confirmed') {
                                        $original = \App\Models\Documento::query()
                                            ->where('cliente_id', $record->cliente_id)
                                            ->where('file_sha256', $record->file_sha256)
                                            ->whereKeyNot($record->id)
                                            ->orderBy('created_at')
                                            ->first();

                                        if (! $original) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Error: Original no encontrado')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $nombreOriginal = (string) ($original->nombre ?? "Documento #{$original->id}");
                                        $nombreOriginal = \Illuminate\Support\Str::limit($nombreOriginal, 80);
                                        $fecha = optional($original->created_at)->format('d/m/Y H:i') ?? '—';

                                        $record->update([
                                            'duplicate_status'        => 'confirmed',
                                            'duplicate_of_id'         => $original->id,
                                            'duplicate_checked_at'    => now(),
                                            'duplicate_checked_by_id' => auth()->id(),
                                            'motivo_rechazo'          => "Duplicado del documento #{$original->id} ({$nombreOriginal} · {$fecha})",
                                            'estado'                  => \App\Enums\DocumentoEstadoEnum::RECHAZADO,
                                        ]);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Rechazado como duplicado')
                                            ->success()
                                            ->send();

                                        // -----------------------------
                                        // SALTO (chain) - panel correcto
                                        // -----------------------------
                                        $qs = [];
                                        parse_str((string) parse_url(request()->headers->get('referer', ''), PHP_URL_QUERY), $qs);

                                        $clienteId = (int) ($qs['cliente'] ?? $record->cliente_id);
                                        $chain = filter_var($qs['chain'] ?? false, FILTER_VALIDATE_BOOL);

                                        if ($chain && $clienteId) {
                                            $seen = array_filter(explode(',', (string) ($qs['seen'] ?? '')));
                                            $seen[] = $record->id;

                                            $nextId = \App\Filament\Resources\DocumentoResource::getNextDocumentId($record->id, $clienteId, $seen);

                                            $panelId = \Filament\Facades\Filament::getCurrentPanel()?->getId() ?? 'admin';

                                            if ($nextId) {
                                                return $livewire->redirect(
                                                    route("filament.{$panelId}.resources.documentos.view", ['record' => $nextId])
                                                        . '?chain=1&cliente=' . $clienteId
                                                        . '&seen=' . implode(',', $seen),
                                                    navigate: true
                                                );
                                            }

                                            return $livewire->redirect(
                                                route("filament.{$panelId}.resources.clientes.view", ['record' => $clienteId]) . '?relation=1',
                                                navigate: true
                                            );
                                        }

                                        // sin chain: refresca
                                        $record->refresh();
                                        $livewire->dispatch('$refresh');
                                        return;
                                    }
                                })

                                // -------------------------------------------------------------
                                // TU MODAL CONTENT (misma UI que ya tenías)
                                // -------------------------------------------------------------
                                ->modalContent(function (\App\Models\Documento $record) {
                                    $grupo = \App\Models\Documento::query()
                                        ->where('cliente_id', $record->cliente_id)
                                        ->whereNotNull('file_sha256')
                                        ->where('file_sha256', $record->file_sha256)
                                        ->orderBy('created_at')
                                        ->orderBy('id')
                                        ->limit(50)
                                        ->get(['id', 'nombre', 'estado', 'created_at', 'ruta', 'mime_type']);

                                    if ($grupo->count() < 2) {
                                        return new \Illuminate\Support\HtmlString('<div class="text-sm text-gray-600">No hay suficientes candidatos para comparar.</div>');
                                    }

                                    $original = $grupo->firstWhere('id', '!=', $record->id) ?? $grupo->first();
                                    $actual = $record;

                                    $renderBadgeEstado = function ($doc) {
                                        $label = $doc->estado instanceof \App\Enums\DocumentoEstadoEnum ? $doc->estado->label() : (string) $doc->estado;
                                        $color = $doc->estado instanceof \App\Enums\DocumentoEstadoEnum ? $doc->estado->color() : 'gray';

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

                                        $url  = \Illuminate\Support\Facades\Storage::disk('public')->url($doc->ruta);
                                        $mime = (string) ($doc->mime_type ?? '');
                                        $ext  = strtolower(pathinfo((string) $doc->ruta, PATHINFO_EXTENSION));

                                        if (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
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

                                        $nombre = e(\Illuminate\Support\Str::limit((string) ($doc->nombre ?? 'Archivo'), 60));
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
                                        $nombre = e(\Illuminate\Support\Str::limit((string) ($doc->nombre ?? "Documento #{$id}"), 70));
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

                                    $htmlIzq = $card($actual, $tagEste, 'Documento que estás revisando', true);
                                    $htmlDer = $card($original, $tagOriginal, 'Documento base (más antiguo)');

                                    return new \Illuminate\Support\HtmlString(<<<HTML
                                    {$banner}
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <div>{$htmlIzq}</div>
                                    <div>{$htmlDer}</div>
                                    </div>
                                    HTML);
                                }),
                        ])->fullWidth(),
                    ])
                    ->visible(fn (\App\Models\Documento $record) =>
                        filled($record->file_sha256) &&
                        $record->duplicate_status === null &&
                        \App\Models\Documento::where('cliente_id', $record->cliente_id)
                            ->where('file_sha256', $record->file_sha256)
                            ->whereKeyNot($record->id)
                            ->exists()
                    ),


                    \Filament\Schemas\Components\Section::make('Información')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('nombre')
                                ->label('📄 Nombre')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->columnSpanFull(),

                            \Filament\Infolists\Components\TextEntry::make('cliente.razon_social')
                                ->label('Documento del cliente')
                                ->state(function ($record) {
                                    $nombre = $record->cliente?->razon_social ?? 'No asignado';
                                    $url = route('filament.admin.resources.clientes.view', ['record' => $record->cliente_id]);

                                    return "<a href='{$url}' target='_blank' class='text-yellow-500 underline font-semibold'>🏢 {$nombre}</a>";
                                })
                                ->html()
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    \Filament\Schemas\Components\Section::make('Estado')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('estado')
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(fn (\App\Enums\DocumentoEstadoEnum $state) => $state->label())
                                ->color(fn (\App\Enums\DocumentoEstadoEnum $state) => $state->color()),

                            \Filament\Infolists\Components\TextEntry::make('tipo.nombre')
                                ->label('Tipo')
                                ->badge()
                                ->color(function ($record) {
                                    $nombre = mb_strtolower((string) ($record->tipo?->nombre ?? ''));
                                    return $nombre === 'sin clasificar'
                                        ? 'warning'
                                        : ($record->tipo?->color ?? 'gray');
                                }),

                            \Filament\Infolists\Components\TextEntry::make('subtipo.nombre')
                                ->label('Subtipo')
                                ->badge()
                                ->color(function ($record) {
                                    $nombre = mb_strtolower((string) ($record->subtipo?->nombre ?? ''));
                                    return $nombre === 'pendiente de clasificar'
                                        ? 'warning'
                                        : ($record->subtipo?->color ?? 'gray');
                                }),

                            \Filament\Infolists\Components\TextEntry::make('subido_por')
                                ->label('👤 Subido por')
                                ->state(function ($record) {
                                    $uploader = $record->user;
                                    $nombre = $uploader?->name ?? '—';

                                    $isCliente = false;
                                    if ($uploader && $record->cliente_id && method_exists($uploader, 'clientes')) {
                                        $isCliente = $uploader->clientes()->whereKey($record->cliente_id)->exists();
                                    }

                                    $label = $isCliente ? 'Cliente' : 'AsesorFy';
                                    $icon  = $isCliente ? 'heroicon-o-arrow-up-right' : 'heroicon-o-arrow-down-left';

                                    $badgeClasses = $isCliente
                                        ? 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-950/40 dark:text-warning-200'
                                        : 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-950/40 dark:text-primary-200';

                                    return "
                                        <div class='flex items-center gap-2'>
                                            <span class='inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$badgeClasses}'>
                                                <span class='fi-icon {$icon}'></span>
                                                {$label}
                                            </span>
                                            <span class='font-medium text-gray-900 dark:text-gray-100'>{$nombre}</span>
                                        </div>
                                    ";
                                })
                                ->html(),

                            \Filament\Infolists\Components\TextEntry::make('created_at')
                                ->label('🕓 Subido el')
                                ->dateTime('d/m/Y - H:i'),
                        ])
                        ->columns(3),

                    \Filament\Schemas\Components\Section::make('📝 Observaciones del cliente')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('observaciones')
                                ->hiddenLabel()
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->visible(fn ($record) => filled($record->observaciones))
                        ->columns(1),

                    \Filament\Schemas\Components\Section::make('🛠️ Observaciones internas AsesorFy')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('observaciones_internas')
                                ->hiddenLabel()
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->visible(fn ($record) => filled($record->observaciones_internas))
                        ->columns(1),

                    // ==========================================
                    // ✅ OPCIÓN A: BLOQUE "ACLARACIÓN" (PREG/RESP)
                    // ==========================================
                    \Filament\Schemas\Components\Section::make('💬 Aclaración')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('aclaracion_chat')
                                ->hiddenLabel()
                                ->state(function ($record) {
                                    $pregunta = trim((string) ($record->aclaracion_pregunta ?? ''));
                                    $respuesta = trim((string) ($record->aclaracion_respuesta ?? ''));

                                    $dtPregunta = '—';
                                    if (filled($record->aclaracion_at)) {
                                        try {
                                            $dtPregunta = \Illuminate\Support\Carbon::parse($record->aclaracion_at)->format('d/m/Y H:i');
                                        } catch (\Throwable $e) {
                                            $dtPregunta = (string) $record->aclaracion_at;
                                        }
                                    }

                                    $dtRespuesta = '—';
                                    if (filled($record->aclaracion_respondida_at)) {
                                        try {
                                            $dtRespuesta = \Illuminate\Support\Carbon::parse($record->aclaracion_respondida_at)->format('d/m/Y H:i');
                                        } catch (\Throwable $e) {
                                            $dtRespuesta = (string) $record->aclaracion_respondida_at;
                                        }
                                    }

                                    $preguntaEsc = e($pregunta ?: '—');
                                    $respuestaEsc = e($respuesta ?: '—');

                                    $showRespuesta = filled($record->aclaracion_respuesta) || filled($record->aclaracion_respondida_at);

                                    $htmlPregunta = <<<HTML
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                            <span class="text-xl font-black text-sky-700 dark:text-sky-200">?</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Pregunta enviada a cliente · {$dtPregunta}</div>
                                            <div class="mt-1 whitespace-pre-line text-sm font-semibold text-gray-900 dark:text-gray-100">{$preguntaEsc}</div>
                                        </div>
                                    </div>
                                </div>
                                HTML;

                                    $htmlRespuesta = '';
                                    if ($showRespuesta) {
                                        $htmlRespuesta = <<<HTML
                                <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-400/20 dark:bg-emerald-950/25">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:ring-emerald-400/20">
                                            <span class="text-xl font-black text-emerald-700 dark:text-emerald-200">↩</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Respuesta del cliente · {$dtRespuesta}</div>
                                            <div class="mt-1 whitespace-pre-line text-sm font-semibold text-gray-900 dark:text-gray-100">{$respuestaEsc}</div>
                                        </div>
                                    </div>
                                </div>
                                HTML;
                                    }

                                    return new \Illuminate\Support\HtmlString($htmlPregunta . $htmlRespuesta);
                                })
                                ->html()
                                ->columnSpanFull(),
                        ])
                        ->visible(fn ($record) => filled($record->aclaracion_pregunta) || filled($record->aclaracion_respuesta) || filled($record->aclaracion_at) || filled($record->aclaracion_respondida_at))
                        ->columns(1),

                    \Filament\Schemas\Components\Section::make('⛔ Documento rechazado')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('motivo_rechazo')
                                ->label('Motivo de rechazo (visible para el cliente)')
                                ->placeholder('—')
                                ->columnSpanFull()
                                ->color('danger'),

                            \Filament\Infolists\Components\TextEntry::make('purge_reason')
                                ->label('Motivo de purga (interno)')
                                ->badge()
                                ->color(fn ($state) => $state === 'sensible' ? 'danger' : 'gray')
                                ->visible(fn ($record) => filled($record->purged_at)),

                            \Filament\Infolists\Components\TextEntry::make('purge_note')
                                ->label('Nota interna (auditoría)')
                                ->placeholder('—')
                                ->columnSpanFull()
                                ->visible(fn ($record) => filled($record->purged_at)),

                            \Filament\Infolists\Components\TextEntry::make('purged_at')
                                ->label('Purgado el')
                                ->dateTime('d/m/Y H:i')
                                ->visible(fn ($record) => filled($record->purged_at)),

                            \Filament\Infolists\Components\TextEntry::make('purgedBy.name')
                                ->label('Purgado por')
                                ->placeholder('—')
                                ->visible(fn ($record) => filled($record->purged_at)),
                        ])
                        ->extraAttributes([
                            'class' => 'bg-red-50 dark:bg-red-950/30 rounded-xl p-4',
                        ])
                        ->visible(fn ($record) => $record->estado === \App\Enums\DocumentoEstadoEnum::RECHAZADO)
                        ->columns(1),
                ]),

            // =========================
            // COLUMNA DERECHA (PREVIEW)
            // =========================

            
            \Filament\Schemas\Components\Section::make('Archivo')
                ->columnSpan(8)
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('purged_notice')
                        ->label('Archivo')
                        ->state(function ($record) {
                            if (filled($record->ruta)) return null;

                            $fecha = null;
                            if (filled($record->purged_at)) {
                                try {
                                    $fecha = \Illuminate\Support\Carbon::parse($record->purged_at)->format('d/m/Y H:i');
                                } catch (\Throwable $e) {
                                    $fecha = (string) $record->purged_at;
                                }
                            }

                            $reason = $record->purge_reason ? strtoupper((string) $record->purge_reason) : null;

                            $linea2 = $reason ? "Motivo: {$reason}" : "Motivo: —";
                            $linea3 = $fecha ? "Fecha: {$fecha}" : "Fecha: —";

                            return "🧹 Archivo eliminado.\n{$linea2}\n{$linea3}";
                        })
                        ->visible(fn ($record) => empty($record->ruta))
                        ->extraAttributes(['class' => 'whitespace-pre-line text-sm text-gray-600']),

                    // ✅ ACCIONES (NO SE PIERDEN)
                    \Filament\Infolists\Components\TextEntry::make('archivo_actions')
                        ->hiddenLabel()
                        ->state(fn () => '')
                        ->belowContent([
    \Filament\Actions\Action::make('ver_archivo')
        ->label('Ver')
        ->icon('heroicon-o-eye')
        ->color('info')
        ->visible(fn ($record) => filled($record->ruta))
        ->url(fn ($record) => \Illuminate\Support\Facades\Storage::url($record->ruta))
        ->openUrlInNewTab(),

    \Filament\Actions\Action::make('descargar_archivo')
        ->label('Descargar')
        ->icon('heroicon-o-arrow-down-tray')
        ->color('warning')
        ->visible(fn ($record) => filled($record->ruta))
        ->action(function ($record) {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($record->ruta);
            $filename = $record->nombre;
            if ($filename && ! str_contains($filename, '.')) {
                $extension = pathinfo($record->ruta, PATHINFO_EXTENSION);
                if ($extension) $filename .= '.' . $extension;
            }
            return response()->download($path, $filename ?: basename($path));
        }),

    // ====== VERIFICAR ======
    \Filament\Actions\Action::make('verificar_documento')
        ->label('Verificar')
        ->icon('heroicon-o-check-circle')
        ->color('success')
        ->visible(fn ($record) => $record->estado !== \App\Enums\DocumentoEstadoEnum::VERIFICADO)
        ->modalWidth('lg')
        ->modalHeading('Verificar y clasificar documento')
        ->fillForm(fn ($record) => [
            'tipo_documento_id'      => $record->tipo_documento_id,
            'subtipo_documento_id'   => $record->subtipo_documento_id,
            'observaciones_internas' => $record->observaciones_internas,
        ])
        ->form([
            \Filament\Forms\Components\Placeholder::make('quick_facturas_header')
                ->hiddenLabel()
                ->content(new \Illuminate\Support\HtmlString('<div class="text-sm text-gray-500">Usa los atajos o clasifica manualmente.</div>')),
            \Filament\Forms\Components\Select::make('tipo_documento_id')
                ->hiddenLabel()
                ->relationship('tipo', 'nombre')
                ->required()
                ->native(false)
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('subtipo_documento_id', null))
                ->hintActions([
                    \Filament\Actions\Action::make('quick_factura_recibida')
                        ->label('Factura recibida')
                        ->color('warning')
                        ->action(fn ($set) => $set('tipo_documento_id', 2) && $set('subtipo_documento_id', 5)),
                    \Filament\Actions\Action::make('quick_factura_emitida')
                        ->label('Factura emitida')
                        ->color('success')
                        ->action(fn ($set) => $set('tipo_documento_id', 2) && $set('subtipo_documento_id', 4)),
                ]),
            \Filament\Forms\Components\Select::make('subtipo_documento_id')
                ->label('Subtipo')
                ->options(fn (callable $get) => filled($get('tipo_documento_id'))
                    ? \App\Models\DocumentoSubtipo::where('documento_categoria_id', $get('tipo_documento_id'))->pluck('nombre', 'id')
                    : [])
                ->required()
                ->native(false)
                ->searchable()
                ->visible(fn (callable $get) => filled($get('tipo_documento_id'))),
            \Filament\Forms\Components\Textarea::make('observaciones_internas')
                ->label('Observaciones internas')
                ->rows(2),
        ])
        ->action(function ($record, array $data, $livewire) {
            $record->update([
                'tipo_documento_id' => $data['tipo_documento_id'],
                'subtipo_documento_id' => $data['subtipo_documento_id'],
                'observaciones_internas' => $data['observaciones_internas'],
                'estado' => \App\Enums\DocumentoEstadoEnum::VERIFICADO,
                'motivo_rechazo' => null,
            ]);

            // Lógica de Salto Centralizada
            $qs = [];
            parse_str((string) parse_url(request()->headers->get('referer', ''), PHP_URL_QUERY), $qs);
            $clienteId = (int) ($qs['cliente'] ?? $record->cliente_id);
            $chain = filter_var($qs['chain'] ?? false, FILTER_VALIDATE_BOOL);

            if ($chain && $clienteId) {
                $seen = array_filter(explode(',', $qs['seen'] ?? ''));
                $seen[] = $record->id;
                
                $nextId = self::getNextDocumentId($record->id, $clienteId, $seen);

                if ($nextId) {
                    return $livewire->redirect(
                        route('filament.admin.resources.documentos.view', ['record' => $nextId]) . '?chain=1&cliente=' . $clienteId . '&seen=' . implode(',', $seen),
                        navigate: true
                    );
                }
                // Si no hay más, volver a lista
                return $livewire->redirect(route('filament.admin.resources.clientes.view', ['record' => $clienteId]) . '?relation=1', navigate: true);
            }
            $livewire->dispatch('$refresh');
        }),

    // ====== PEDIR ACLARACIÓN ======
   \Filament\Actions\Action::make('pedir_aclaracion')
    ->label('Aclaración')
    ->icon('heroicon-o-chat-bubble-left-ellipsis')
    ->color('info')
    ->modalWidth('lg')
    ->form([
        \Filament\Forms\Components\Placeholder::make('iafy_notice')
            ->hiddenLabel()
          ->content(new \Illuminate\Support\HtmlString(
    '<div class="rounded-2xl border border-sky-200/60 bg-gradient-to-br from-sky-50 via-white to-indigo-50 px-6 py-5 text-sm text-slate-800 shadow-sm ring-1 ring-sky-300/20 dark:border-sky-400/20 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950/40 dark:text-slate-100">
        
        <div class="flex flex-col items-center text-center">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="w-16 h-16">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0 1 12 12.75Zm0 0c2.883 0 5.647.508 8.207 1.44a23.91 23.91 0 0 1-1.152 6.06M12 12.75c-2.883 0-5.647.508-8.208 1.44.125 2.104.52 4.136 1.153 6.06M12 12.75a2.25 2.25 0 0 0 2.248-2.354M12 12.75a2.25 2.25 0 0 1-2.248-2.354M12 8.25c.995 0 1.971-.08 2.922-.236.403-.066.74-.358.795-.762a3.778 3.778 0 0 0-.399-2.25M12 8.25c-.995 0-1.97-.08-2.922-.236-.402-.066-.74-.358-.795-.762a3.734 3.734 0 0 1 .4-2.253M12 8.25a2.25 2.25 0 0 0-2.248 2.146M12 8.25a2.25 2.25 0 0 1 2.248 2.146M8.683 5a6.032 6.032 0 0 1-1.155-1.002c.07-.63.27-1.222.574-1.747m.581 2.749A3.75 3.75 0 0 1 15.318 5m0 0c.427-.283.815-.62 1.155-.999a4.471 4.471 0 0 0-.575-1.752M4.921 6a24.048 24.048 0 0 0-.392 3.314c1.668.546 3.416.914 5.223 1.082M19.08 6c.205 1.08.337 2.187.392 3.314a23.882 23.882 0 0 1-5.223 1.082" />
            </svg>

            <div class="mt-2 inline-flex items-center gap-2 rounded-full bg-sky-600 px-3 py-1 text-xs font-semibold text-white shadow-sm dark:bg-sky-500">
                IAFy · Inteligencia Artificial
            </div>

            <div class="mt-4 space-y-3">
                <div class="text-base font-bold text-slate-900 dark:text-white">
                    Aviso automático al cliente (email + notificación)
                </div>

                <div class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                    <b>IAFy</b> enviará automáticamente un <b>email</b> y una <b>notificación</b> al cliente indicándole que tiene
                    <b>documentos pendientes de respuesta</b>.
                </div>

                <div class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                    IAFy <b>no envía un email por documento</b>: agrupa todo en un solo aviso y comprueba si <b>ya se le avisó hoy</b>.
                    Si hoy ya se avisó, no se vuelve a enviar (máx. <b>1 aviso/día</b> por cliente).
                </div>

                <div class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                    Si el cliente no contesta, IAFy lo seguirá recordando <b>cada día</b> hasta que responda.
                    <b>El asesor no tiene que hacer nada.</b>
                </div>
            </div>
        </div>
    </div>'
))


,

        \Filament\Forms\Components\Textarea::make('aclaracion_pregunta')
            ->label('Pregunta')
            ->required()
            ->rows(3),
    ])
    ->visible(fn ($record) => $record->estado !== \App\Enums\DocumentoEstadoEnum::NECESITA_ACLARACION && blank($record->aclaracion_respondida_at))
    ->action(function ($record, array $data, $livewire) {

        $record->update([
            'estado' => \App\Enums\DocumentoEstadoEnum::NECESITA_ACLARACION,
            'aclaracion_pregunta' => $data['aclaracion_pregunta'],
            'aclaracion_at' => now(),
            'motivo_rechazo' => null,
        ]);

        // ✅ Digest (1/día): programamos aviso agregado al cliente (email + notificación)
        \App\Jobs\NotificarPendientesRespuestaClienteJob::dispatch((int) $record->cliente_id)
            ->delay(now()->addMinutes(3));

        // ✅ Feedback al asesor (siempre, aunque el Job decida no enviar por “ya avisado hoy”)
        \Filament\Notifications\Notification::make()
            ->title('IAFy: aviso al cliente programado')
            ->body('Se enviará email y notificación si hoy no se avisó ya (máx. 1/día).')
            ->success()
            ->send();

        // --- tu lógica de salto centralizada (la dejo igual) ---
        $qs = [];
        parse_str((string) parse_url(request()->headers->get('referer', ''), PHP_URL_QUERY), $qs);
        $clienteId = (int) ($qs['cliente'] ?? $record->cliente_id);
        $chain = filter_var($qs['chain'] ?? false, FILTER_VALIDATE_BOOL);

        if ($chain && $clienteId) {
            $seen = array_filter(explode(',', $qs['seen'] ?? ''));
            $seen[] = $record->id;

            $nextId = self::getNextDocumentId($record->id, $clienteId, $seen);

            if ($nextId) {
                return $livewire->redirect(
                    route('filament.admin.resources.documentos.view', ['record' => $nextId])
                        . '?chain=1&cliente=' . $clienteId
                        . '&seen=' . implode(',', $seen),
                    navigate: true
                );
            }

            return $livewire->redirect(
                route('filament.admin.resources.clientes.view', ['record' => $clienteId]) . '?relation=1',
                navigate: true
            );
        }

        $livewire->dispatch('$refresh');
    }),


    // ====== RECHAZAR ======
    \Filament\Actions\Action::make('rechazar_documento')
        ->label('Rechazar')
        ->icon('heroicon-o-x-circle')
        ->color('danger')
        ->form([
            \Filament\Forms\Components\Textarea::make('motivo_rechazo')
                ->label('Motivo')
                ->required()
                ->rows(3),
        ])
        ->visible(fn ($record) => $record->estado !== \App\Enums\DocumentoEstadoEnum::RECHAZADO)
        ->action(function ($record, array $data, $livewire) {
            $record->update([
                'estado' => \App\Enums\DocumentoEstadoEnum::RECHAZADO,
                'motivo_rechazo' => $data['motivo_rechazo'],
            ]);

            // Lógica de Salto Centralizada
            $qs = [];
            parse_str((string) parse_url(request()->headers->get('referer', ''), PHP_URL_QUERY), $qs);
            $clienteId = (int) ($qs['cliente'] ?? $record->cliente_id);
            $chain = filter_var($qs['chain'] ?? false, FILTER_VALIDATE_BOOL);

            if ($chain && $clienteId) {
                $seen = array_filter(explode(',', $qs['seen'] ?? ''));
                $seen[] = $record->id;
                
                $nextId = self::getNextDocumentId($record->id, $clienteId, $seen);

                if ($nextId) {
                    return $livewire->redirect(
                        route('filament.admin.resources.documentos.view', ['record' => $nextId]) . '?chain=1&cliente=' . $clienteId . '&seen=' . implode(',', $seen),
                        navigate: true
                    );
                }
                return $livewire->redirect(route('filament.admin.resources.clientes.view', ['record' => $clienteId]) . '?relation=1', navigate: true);
            }
            $livewire->dispatch('$refresh');
        }),

    // ====== SALTAR ======
\Filament\Actions\Action::make('saltar_documento')
    ->label('Saltar')
    ->icon('heroicon-o-forward')
    ->color('gray')
   
    ->visible(fn ($record) => $record->estado === \App\Enums\DocumentoEstadoEnum::PENDIENTE)
    ->action(function ($record, $livewire) {
        // ... (el código de dentro está bien, déjalo igual) ...
        $qs = [];
        parse_str((string) parse_url(request()->headers->get('referer', ''), PHP_URL_QUERY), $qs);
        $clienteId = (int) ($qs['cliente'] ?? $record->cliente_id);
        $chain = filter_var($qs['chain'] ?? false, FILTER_VALIDATE_BOOL);

        if ($chain && $clienteId) {
            $seen = array_filter(explode(',', $qs['seen'] ?? ''));
            $seen[] = $record->id; 
            
            $nextId = self::getNextDocumentId($record->id, $clienteId, $seen);

            if ($nextId) {
                return $livewire->redirect(
                    route('filament.admin.resources.documentos.view', ['record' => $nextId]) . '?chain=1&cliente=' . $clienteId . '&seen=' . implode(',', $seen),
                    navigate: true
                );
            }
            return $livewire->redirect(route('filament.admin.resources.clientes.view', ['record' => $clienteId]) . '?relation=1', navigate: true);
        }
    }),

    // ====== AMPLIAR IMAGEN (sin cambios, déjala como estaba) ======
    \Filament\Actions\Action::make('ampliar_imagen')
        ->label('Ampliar')
        ->icon('heroicon-o-magnifying-glass-plus')
        ->color('gray')
        ->visible(fn ($record) => filled($record->ruta) && str_starts_with((string) $record->mime_type, 'image/'))
        ->modalContent(function ($record) {
             // ... tu código de modal visual ...
             $url = \Illuminate\Support\Facades\Storage::url($record->ruta);
             return new \Illuminate\Support\HtmlString("<img src='{$url}' class='w-full rounded-lg' />");
        })
        ->modalSubmitAction(false)
        ->modalCancelActionLabel('Cerrar'),
])
                        ->visible(fn ($record) => filled($record->ruta)),

                        \Filament\Infolists\Components\TextEntry::make('aclaracion_contestada_notice')
                        ->hiddenLabel()
                        ->state('✅ Cliente ha contestado aclaración, rechazar o validar')
                        ->html()
                        ->extraAttributes([
                            'class' => 'rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-800 dark:border-sky-400/20 dark:bg-sky-950/30 dark:text-sky-200',
                        ])
                        ->visible(fn ($record) => filled($record->aclaracion_respondida_at)),

                    // Imagen normal (sin modal)
                    \Filament\Infolists\Components\TextEntry::make('preview_imagen')
                        ->label('Vista previa')
                        ->state(function ($record) {
                            $url = \Illuminate\Support\Facades\Storage::url($record->ruta);

                            return <<<HTML
                            <div style="display:flex;justify-content:center;align-items:center;background:#0f0f0f;padding:1rem;border-radius:12px;">
                                <img src="{$url}" style="max-width:100%;max-height:820px;width:auto;height:auto;object-fit:contain;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.4);">
                            </div>
                            HTML;
                        })
                        ->html()
                        ->visible(fn ($record) => filled($record->ruta) && str_starts_with((string) $record->mime_type, 'image/')),

                    \Joaopaulolndev\FilamentPdfViewer\Infolists\Components\PdfViewerEntry::make('preview_pdf')
                        ->label('Vista previa')
                        ->fileUrl(fn ($record) => \Illuminate\Support\Facades\Storage::url($record->ruta))
                        ->minHeight('820px')
                        ->visible(fn ($record) => filled($record->ruta) && (string) $record->mime_type === 'application/pdf'),

                    \Filament\Infolists\Components\TextEntry::make('preview_otro')
                        ->label('Vista previa')
                        ->state(function ($record) {
                            $mime = (string) ($record->mime_type ?? '');
                            $ruta = (string) ($record->ruta ?? '');

                            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

                            $isExcel = in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true)
                                || str_contains($mime, 'spreadsheet')
                                || str_contains($mime, 'excel')
                                || $mime === 'text/csv';

                            $isWord = in_array($ext, ['doc', 'docx', 'odt'], true)
                                || str_contains($mime, 'word')
                                || str_contains($mime, 'officedocument.wordprocessingml');

                            $title = $isExcel ? 'Archivo Excel / Hoja de cálculo'
                                : ($isWord ? 'Documento Word' : 'Archivo');

                            $hint = 'Este tipo de archivo no admite vista previa. Usa "Ver" o "Descargar".';

                            $svgFile = $isExcel
                                ? resource_path('svg/excel2.svg')
                                : ($isWord
                                    ? resource_path('svg/doc.svg')
                                    : resource_path('svg/tipodocumento.svg')
                                );

                            $svg = '';
                            if (is_file($svgFile)) {
                                $svg = (string) file_get_contents($svgFile);

                                if (str_contains($svg, '<svg') && ! str_contains($svg, 'class=')) {
                                    $svg = preg_replace('/<svg\b/', '<svg class="h-7 w-7"', $svg, 1);
                                }

                                $svg = preg_replace('/class="([^"]*)"/', 'class="$1 h-7 w-7"', $svg, 1);
                                $svg = preg_replace('/\s(width|height)="[^"]*"/', '', $svg);
                            }

                            return new \Illuminate\Support\HtmlString(<<<HTML
                            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-white/5">
                                        <div class="text-gray-700 dark:text-gray-200">
                                            {$svg}
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{$title}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">{$hint}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2 break-all">{$ruta}</div>
                                    </div>
                                </div>
                            </div>
                            HTML);
                        })
                        ->html()
                        ->visible(fn ($record) =>
                            filled($record->ruta)
                            && ! str_starts_with((string) $record->mime_type, 'image/')
                            && (string) $record->mime_type !== 'application/pdf'
                        ),
                ]),
        ]);
}



   public static function table(Table $table): Table
{
    return $table
        ->recordUrl(null)
        ->columns([
            TextColumn::make('user.name')
                ->label('Subido por')
                ->getStateUsing(function ($record) {
                    $user = $record->user;
                    if (! $user) return 'Usuario desconocido';

                    $nombre = $user->full_name ?? $user->name;
                    $tipo = $user->tipoDeUsuario();

                    if ($tipo === 'Trabajador') {
                        $roles = $user->roles->pluck('name')->implode(', ');
                        return "{$nombre} ({$roles})";
                    }

                    return "{$nombre} ({$tipo})";
                }),

            TextColumn::make('cliente_origen')
                ->label('Origen')
                ->icon('icon-customer')
                ->iconPosition('before')
                ->badge()
                ->searchable()
                ->getStateUsing(function ($record) {
                    if ($record->cliente) {
                        return $record->cliente->razon_social;
                    }

                    if ($record->documentable_type === \App\Models\Lead::class) {
                        return 'Lead';
                    }

                    if ($record->documentable_type === \App\Models\Proyecto::class) {
                        return 'Proyecto';
                    }

                    return '—';
                })
                ->color(function ($record) {
                    if ($record->cliente) return 'warning';
                    if ($record->documentable_type === \App\Models\Lead::class) return 'info';
                    if ($record->documentable_type === \App\Models\Proyecto::class) return 'primary';
                    return 'gray';
                })
                ->url(function ($record) {
                    if ($record->cliente_id) {
                        return \App\Filament\Resources\ClienteResource::getUrl('view', ['record' => $record->cliente_id]);
                    }

                    if ($record->documentable_type === \App\Models\Lead::class) {
                        return \App\Filament\Resources\LeadResource::getUrl('view', ['record' => $record->documentable_id]);
                    }

                    if ($record->documentable_type === \App\Models\Proyecto::class) {
                        return \App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $record->documentable_id]);
                    }

                    return null;
                })
                ->openUrlInNewTab(),

            TextColumn::make('tipo.nombre')
                ->label('Tipo')
                ->badge()
                ->color(fn ($record) => $record->tipo->color ?? 'gray'),

            TextColumn::make('subtipo.nombre')
                ->label('Subtipo')
                ->badge()
                ->color('gray'),

            // ✅ NUEVO: Estado enum (badge)
            TextColumn::make('estado')
                ->label('Estado')
                ->badge()
                ->formatStateUsing(fn (DocumentoEstadoEnum $state) => $state->label())
                ->color(fn (DocumentoEstadoEnum $state) => $state->color())
                ->sortable(),

            TextColumn::make('ruta')
                ->label('Archivo')
                ->url(fn ($record) => Storage::url($record->ruta), true)
                ->openUrlInNewTab()
                ->formatStateUsing(fn ($record) => $record->nombre),

            TextColumn::make('observaciones')
                ->label('Observaciones')
                ->limit(30),

            // compat visual
            IconColumn::make('verificado')
                ->label('Verificado')
                ->boolean()
                ->trueIcon('heroicon-m-check-circle')
                ->falseIcon('heroicon-m-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),

            IconColumn::make('mime_type')
                ->label('Tipo')
                ->icon(function ($record) {
                    $mime = $record->mime_type ?? '';
                    $extension = strtolower(pathinfo($record->ruta, PATHINFO_EXTENSION));

                    if ($mime === 'image/png' || $extension === 'png') return 'icon-png';
                    if ($mime === 'image/jpeg' && $extension === 'jpg') return 'icon-jpg';
                    if ($mime === 'image/jpeg' && $extension === 'jpeg') return 'icon-jpeg';
                    if ($mime === 'application/pdf') return 'icon-pdf';

                    if (in_array($mime, [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])) return 'icon-doc';

                    if (in_array($mime, [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])) return 'icon-excel';

                    return 'heroicon-o-question-mark-circle';
                })
                ->tooltip(fn ($record) => $record->mime_type ?? 'Desconocido')
                ->color(function ($record) {
                    $mime = $record->mime_type ?? '';
                    if (str_starts_with($mime, 'image/')) return 'info';
                    if ($mime === 'application/pdf') return 'danger';

                    if (in_array($mime, [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])) return 'primary';

                    if (in_array($mime, [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])) return 'success';

                    return 'gray';
                }),

            TextColumn::make('created_at')
                ->label('Subido el')
                ->dateTime('d/m/Y H:i')
                ->sortable(),

                Tables\Columns\TextColumn::make('purged_at')
    ->label('Purgado')
    ->dateTime('d/m/Y H:i')
   ->toggleable(isToggledHiddenByDefault: true)
    ->sortable()
    ->placeholder('—'),

    Tables\Columns\TextColumn::make('purge_reason')
        ->label('Motivo purga')
        ->badge()
        ->color(fn ($state) => $state === 'sensible' ? 'danger' : 'gray')
        ->toggleable(isToggledHiddenByDefault: true)
        ->placeholder('—'),

    Tables\Columns\TextColumn::make('purgedBy.name')
        ->label('Purgado por')
        ->toggleable(isToggledHiddenByDefault: true)
        ->placeholder('—'),

    Tables\Columns\IconColumn::make('hidden_in_portal')
        ->label('Oculto portal')
        ->boolean()
        ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->options(Cliente::pluck('razon_social', 'id'))
                    ->searchable()
                    ->native(false),

                // ✅ NUEVO filtro por estado
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        DocumentoEstadoEnum::PENDIENTE->value => DocumentoEstadoEnum::PENDIENTE->label(),
                        DocumentoEstadoEnum::VERIFICADO->value => DocumentoEstadoEnum::VERIFICADO->label(),
                        DocumentoEstadoEnum::RECHAZADO->value => DocumentoEstadoEnum::RECHAZADO->label(),
                    ])
                    ->native(false),

                // mantengo tu ternary por si lo quieres, pero ya no es necesario
                // si prefieres lo quitamos
                TernaryFilter::make('verificado')
                    ->label('Verificado')
                    ->trueLabel('Solo verificados')
                    ->falseLabel('Solo no verificados')
                    ->native(false),

                SelectFilter::make('tipo_documento_id')
                    ->label('Tipo')
                    ->options(DocumentoCategoria::pluck('nombre', 'id'))
                    ->searchable()
                    ->native(false),

                SelectFilter::make('subtipo_documento_id')
                    ->label('Subtipo')
                    ->options(DocumentoSubtipo::pluck('nombre', 'id'))
                    ->searchable()
                    ->native(false),

                SelectFilter::make('mime_type')
                    ->label('Buscar por extensión')
                    ->options([
                        'application/pdf' => 'PDF',
                        'image/png' => 'Imagen PNG',
                        'image/jpeg' => 'Imagen JPG/JPEG',
                        'application/msword' => 'Word (doc)',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (docx)',
                        'application/vnd.ms-excel' => 'Excel (xls)',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (xlsx)',
                    ]),

                SelectFilter::make('documentable_type')
                    ->label('Tipo de asociado')
                    ->options([
                        'App\Models\Cliente' => 'Cliente',
                        'App\Models\Proyecto' => 'Proyecto',
                        'App\Models\Lead' => 'Lead',
                    ]),

                DateRangeFilter::make('created_at')
                    ->label('Subido')
                    ->placeholder('Rango de fechas a buscar'),

                DateRangeFilter::make('updated_at')
                    ->label('Actualizado')
                    ->placeholder('Rango de fechas a buscar'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(7)
            ->recordActions([
                // ✅ NUEVAS acciones (modal motivo obligatorio)
                Action::make('verificar')
                    ->label('Verificar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->estado !== DocumentoEstadoEnum::VERIFICADO)
                    ->action(function ($record) {
                        $record->estado = DocumentoEstadoEnum::VERIFICADO;
                        $record->verificado = true;
                        $record->motivo_rechazo = null;
                        $record->save();

                        Notification::make()->title('Documento verificado')->success()->send();
                    }),

                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Rechazar documento')
                    ->modalDescription('Indica el motivo (obligatorio). El cliente lo verá en el portal.')
                    ->form([
                        FormTextarea::make('motivo_rechazo')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4),
                    ])
                    ->visible(fn ($record) => $record->estado !== DocumentoEstadoEnum::RECHAZADO)
                    ->action(function ($record, array $data) {
                        $record->estado = DocumentoEstadoEnum::RECHAZADO;
                        $record->verificado = false;
                        $record->motivo_rechazo = $data['motivo_rechazo'];
                        $record->save();

                        Notification::make()->title('Documento rechazado')->danger()->send();
                    }),

                    

                EditAction::make()
                
                    ->url(fn ($record) => DocumentoResource::getUrl('edit', ['record' => $record]))
            ->openUrlInNewTab(),
                ViewAction::make()->label('Ver')
                ->url(fn ($record) => DocumentoResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
        
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            //'create' => CreateDocumento::route('/create'),
            'edit' => EditDocumento::route('/{record}/edit'),
            'view' => ViewDocumento::route('/{record}/view'),

        ];
    }

            public static function canCreate(): bool
        {
            return false;
        }

    /**
     * Busca el siguiente documento PENDIENTE del cliente para la cadena de revisión.
     */
    public static function getNextDocumentId(int $currentId, int $clienteId, array $seenIds): ?int
    {
        // Base: Documentos de este cliente, pendientes, con archivo y no purgados
        $query = \App\Models\Documento::query()
            ->where('cliente_id', $clienteId)
            ->where('estado', \App\Enums\DocumentoEstadoEnum::PENDIENTE->value)
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->whereNull('purged_at')
            ->whereKeyNot($currentId)
            ->whereNotIn('id', $seenIds);

        // Datos del actual para buscar el siguiente cronológicamente
        $currentDoc = \App\Models\Documento::find($currentId);
        $created_at = $currentDoc?->created_at ?? now();

        // Intento 1: Buscar uno posterior en fecha/ID (hacia adelante)
        $next = (clone $query)
            ->where(function ($q) use ($created_at, $currentId) {
                $q->where('created_at', '>', $created_at)
                ->orWhere(function ($q2) use ($created_at, $currentId) {
                    $q2->where('created_at', $created_at)->where('id', '>', $currentId);
                });
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        // Intento 2: Si no hay siguiente, volver al principio (Loop)
        if (!$next) {
            $next = (clone $query)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->first();
        }

        return $next?->id;
    }    

    /* public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\DocumentoResource\Widgets\DocumentoStats::class,
        ];
    } */


}
