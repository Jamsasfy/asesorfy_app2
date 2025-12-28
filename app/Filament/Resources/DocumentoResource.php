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
        return $user->can('view_any_documento');
    }  
    if ($user->hasRole('coordinador')) {
         return $user->can('view_any_documento');
    } 

    return false; // Por defecto, no mostrar para otros roles
}





    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->helperText('Selecciona el tipo generico o familia del documento que vas a subir al cliente.')
                ->relationship('tipo', 'nombre')
                ->required()
                ->live(), // <- IMPORTANTE
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
                ->directory('documentos') // o la carpeta que uses
                ->maxSize(32768) // 32 MB
                ->required()
                ->moveFiles() // ✅ Esto evita la subida inmediata
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                    'application/msword', // .doc
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // ✅ .docx
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->preserveFilenames(false)
                ->visibility('public')
                ->visible(fn (string $context) => $context === 'create') // 👈 Aquí está la clave

                ->validationMessages([
                    'required' => 'Por favor, selecciona un archivo.',
                    'accepted_file_types' => 'Solo se permiten PDF, imágenes, Word y Excel.',
                    'max' => 'El archivo excede el tamaño máximo de 32 MB.',
                ])
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $set('nombre', $state->getClientOriginalName()); // ✅ guarda el nombre real
                    }
                }),
            TextInput::make('nombre')
                ->label('Nombre del documento')
                ->required()
               // ->searchable()
                ->maxLength(255)
                ->placeholder('Se rellenará automáticamente con el nombre del archivo')
                ->helperText('Puedes modificar el nombre si lo deseas.'),
            Textarea::make('observaciones')
                ->label('Observaciones')
                ->columnSpanFull(),

             // Selección de cliente solo visible para roles internos
        Select::make('cliente_id')
        ->label('Cliente')      
         ->relationship(
    name: 'cliente',
    titleAttribute: 'razon_social',
    modifyQueryUsing: function (Builder $query) {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            // Para depurar qué roles tiene el usuario actual (puedes descomentar temporalmente):
            // \Illuminate\Support\Facades\Log::info('Usuario en form cliente_id:', ['email's => $user->email, 'roles' => $user->getRoleNames()->toArray()]);

            if ($user->hasRole('super_admin')) {
                // El super_admin ve todos los clientes, no se añade ningún filtro aquí.
                // La consulta base de la relación se usará tal cual.
            } elseif ($user->hasRole('asesor')) {
                // Si NO es super_admin PERO SÍ es asesor, filtramos por sus clientes.
                $query->where('asesor_id', $user->id);
            }

        } else {
            // No hay usuario autenticado (no debería ocurrir en Filament)
            $query->whereRaw('1 = 0'); // No muestra nada
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


            // 2️⃣ Selecciona el registro concreto del modelo elegido
            Select::make('documentable_id')
            ->label('Selecciona el registro')
            ->required()
            ->searchable()
            ->options(function (callable $get) {
                $type = $get('documentable_type');
                $clienteId = $get('cliente_id');

                if ($type === 'App\Models\Cliente' && $clienteId) {
                    // Solo deja elegir el cliente seleccionado
                    $cliente = Cliente::find($clienteId);
                    return $cliente ? [$cliente->id => $cliente->razon_social] : [];
                }
                if ($type === 'App\Models\Proyecto' && $clienteId) {
                    // Filtra proyectos SOLO de ese cliente
                    return Proyecto::where('cliente_id', $clienteId)
                        ->pluck('nombre', 'id');
                }
                return [];
            })
            ->visible(fn (callable $get) => filled($get('documentable_type')))
            ->helperText('Primero selecciona cliente y después el tipo de asociación.'),

    ]);
    }

public static function infolist(Schema $schema): Schema
{
    return $schema
        ->columns(1) // 🔑 TODO en una sola columna
        ->components([

            /* ===============================
             | INFO + ESTADO (FULL WIDTH)
             =============================== */
            Section::make()
                ->schema([
                    Section::make('Información general')
                        ->schema([
                            TextEntry::make('nombre')
                                ->label('📄 Nombre')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('tipo.nombre')
                                ->label('📁 Tipo')
                                ->badge()
                                ->color(fn ($record) => $record->tipo->color ?? 'gray'),

                            TextEntry::make('subtipo.nombre')
                                ->label('📂 Subtipo')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('observaciones')
                                ->label('📝 Observaciones'),

                            TextEntry::make('cliente.razon_social')
                                ->label('Cliente')
                                ->state(function ($record) {
                                    $nombre = $record->cliente?->razon_social ?? 'No asignado';
                                    $url = route(
                                        'filament.admin.resources.clientes.view',
                                        ['record' => $record->cliente_id]
                                    );

                                    return "<a href='{$url}' target='_blank'
                                            class='text-yellow-500 underline font-semibold'>
                                            🏢 {$nombre}
                                        </a>";
                                })
                                ->html(),
                        ])
                        ->columns(5),

                    Section::make('Estado')
                        ->schema([
                         TextEntry::make('verificado')
    ->label('Verificado')
    ->formatStateUsing(fn (bool $state) =>
        $state ? '✅ Verificado' : '⚠️ No verificado'
    )
    ->color(fn (bool $state) =>
        $state ? 'success' : 'danger'
    )
    ->belowContent([
        Action::make('toggle_verificacion')
            ->label(fn ($record) =>
                $record->verificado
                    ? 'Quitar verificación'
                    : 'Verificar documento'
            )
            ->icon(fn ($record) =>
                $record->verificado
                    ? 'heroicon-o-x-circle'
                    : 'heroicon-o-check-circle'
            )
            ->color(fn ($record) =>
                $record->verificado ? 'danger' : 'success'
            )
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn ($record) =>
                $record->verificado
                    ? '¿Quitar verificación?'
                    : '¿Verificar documento?'
            )
            ->action(function ($record) {
                $record->verificado = ! $record->verificado;
                $record->save();

                Notification::make()
                    ->title(
                        $record->verificado
                            ? 'Documento verificado'
                            : 'Verificación retirada'
                    )
                    ->success()
                    ->send();
            })
            ->visible(fn ($record) =>
                auth()->user()?->can('verificar', $record) ?? false
            ),
        ]),


                            TextEntry::make('user.name')
                                ->label('👤 Subido por')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('created_at')
                                ->label('🕓 Subido el')
                                ->dateTime('d/m/Y - H:i'),
                        ])
                        ->columns(3),
                ]),

            /* ===============================
             | ARCHIVO (FULL WIDTH ABAJO)
             =============================== */
            Section::make('Archivo')
                ->schema([
                    // 🔘 BOTONES VER / DESCARGAR
                    TextEntry::make('archivo_actions')
    ->label(false)
    ->state(fn () => '')
    ->belowContent([
        Action::make('ver_archivo')
            ->label('Ver')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->url(fn ($record) => Storage::url($record->ruta))
            ->openUrlInNewTab(),

      Action::make('descargar_archivo')
    ->label('Descargar')
    ->icon('heroicon-o-arrow-down-tray')
    ->color('success')
    ->action(function ($record) {

        $path = Storage::disk('public')->path($record->ruta);

        // 🔑 Nombre final con extensión REAL
        $filename = $record->nombre;

        if (! str_contains($filename, '.')) {
            $extension = pathinfo($record->ruta, PATHINFO_EXTENSION);
            $filename .= '.' . $extension;
        }

        return response()->download($path, $filename);
    }),

        Action::make('toggle_verificacion')
            ->label(fn ($record) =>
                $record->verificado ? 'Quitar verificación' : 'Verificar'
            )
            ->icon(fn ($record) =>
                $record->verificado
                    ? 'heroicon-o-x-circle'
                    : 'heroicon-o-check-circle'
            )
            ->color(fn ($record) =>
                $record->verificado ? 'danger' : 'success'
            )
            ->requiresConfirmation()
            ->action(function ($record) {
                $record->verificado = ! $record->verificado;
                $record->save();

                Notification::make()
                    ->title(
                        $record->verificado
                            ? 'Documento verificado'
                            : 'Verificación retirada'
                    )
                    ->success()
                    ->send();
            })
            ->visible(fn ($record) =>
                auth()->user()?->can('verificar', $record) ?? false
            ),
    ])
    ->visible(fn ($record) => filled($record->ruta)),


                    // 🖼 Imagen
                  TextEntry::make('preview_imagen')
    ->label('Vista previa de imagen')
    ->state(function ($record) {
        $url = Storage::url($record->ruta);

        return <<<HTML
            <div style="
                display: flex;
                justify-content: center;
                align-items: center;
                background: #0f0f0f;
                padding: 1rem;
                border-radius: 12px;
            ">
                <img 
                    src="$url"
                    style="
                        max-width: 100%;
                        max-height: 600px;
                        width: auto;
                        height: auto;
                        object-fit: contain;
                        border-radius: 10px;
                        box-shadow: 0 4px 12px rgba(0,0,0,.4);
                    "
                >
            </div>
        HTML;
    })
    ->html()
    ->visible(fn ($record) => str_starts_with($record->mime_type, 'image/')),
                    // 📄 PDF
                    PdfViewerEntry::make('preview_pdf')
                        ->label('Vista previa del PDF')
                        ->fileUrl(fn ($record) => Storage::url($record->ruta))
                        ->minHeight('700px')
                        ->visible(fn ($record) =>
                            $record->mime_type === 'application/pdf'
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
                        if ($record->cliente) {
                            return 'warning';   // Cliente
                        }

                        if ($record->documentable_type === \App\Models\Lead::class) {
                            return 'info';      // Lead
                        }

                        if ($record->documentable_type === \App\Models\Proyecto::class) {
                            return 'primary';  // Proyecto
                        }

                        return 'gray';
                    })
                    ->url(function ($record) {
                        // 🔗 Cliente
                        if ($record->cliente_id) {
                            return \App\Filament\Resources\ClienteResource::getUrl('view', [
                                'record' => $record->cliente_id,
                            ]);
                        }

                        // 🔗 Lead
                        if ($record->documentable_type === \App\Models\Lead::class) {
                            return \App\Filament\Resources\LeadResource::getUrl('view', [
                                'record' => $record->documentable_id,
                            ]);
                        }

                        // 🔗 Proyecto
                        if ($record->documentable_type === \App\Models\Proyecto::class) {
                            return \App\Filament\Resources\ProyectoResource::getUrl('view', [
                                'record' => $record->documentable_id,
                            ]);
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
            TextColumn::make('ruta')
                ->label('Archivo')
                ->url(fn ($record) => Storage::url($record->ruta), true)
                ->openUrlInNewTab()
                ->formatStateUsing(fn ($record) => $record->nombre),
            TextColumn::make('observaciones')
                ->label('Observaciones')
                ->limit(30),


                IconColumn::make('verificado')
                ->label('Verificado')
                ->boolean()
                ->trueIcon('heroicon-m-check-circle')
                ->falseIcon('heroicon-m-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->action(function ($record, $livewire) {
                    // Verificamos si el usuario tiene el permiso "verificado_documento"
                    if (! auth()->user()->can('verificar', $record)) {

                        Notification::make()
                            ->title('No tienes permiso para verificar este documento.')
                            ->danger()
                            ->send();
                        return;
                    }
                    // Si tiene permiso, alterna el estado de verificado
                    $record->verificado = ! $record->verificado;
                    $record->save();

                    Notification::make()
                        ->title($record->verificado ? 'Documento verificado' : 'Verificación retirada')
                        ->success()
                        ->send();
                })
                ->tooltip(fn ($record) => $record->verificado
                    ? 'Marcar como NO verificado'
                    : 'Marcar como verificado')
                ->extraAttributes(['style' => 'cursor: pointer;']),


                IconColumn::make('mime_type')
                ->label('Tipo')
                ->icon(function ($record) {
                    $mime = $record->mime_type ?? '';
                    $extension = strtolower(pathinfo($record->ruta, PATHINFO_EXTENSION));

                    // Tipos de imagen diferenciados
                    if ($mime === 'image/png' || $extension === 'png') {
                        return 'icon-png'; // PNG
                    }

                    if ($mime === 'image/jpeg' && $extension === 'jpg') {
                        return 'icon-jpg'; // JPG
                    }

                    if ($mime === 'image/jpeg' && $extension === 'jpeg') {
                        return 'icon-jpeg'; // JPEG
                    }

                    // PDF
                    if ($mime === 'application/pdf') {
                        return 'icon-pdf';
                    }

                    // Word
                    if (in_array($mime, [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])) {
                        return 'icon-doc';
                    }

                    // Excel
                    if (in_array($mime, [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])) {
                        return 'icon-excel';
                    }

                    // Por defecto
                    return 'heroicon-o-question-mark-circle';
                })
                ->tooltip(fn ($record) => $record->mime_type ?? 'Desconocido')
                ->color(function ($record) {
                    $mime = $record->mime_type ?? '';

                    if (str_starts_with($mime, 'image/')) {
                        return 'info'; // Azul
                    }

                    if ($mime === 'application/pdf') {
                        return 'danger'; // Rojo
                    }

                    if (in_array($mime, [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])) {
                        return 'primary'; // Azul oscuro
                    }

                    if (in_array($mime, [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])) {
                        return 'success'; // Verde
                    }

                    return 'gray'; // Por defecto
                }),

                    TextColumn::make('created_at')
                        ->label('Subido el')
                        ->dateTime('d/m/Y H:i')
                        ->sortable(),

                ])
                ->defaultSort('created_at', 'desc')
            ->filters([
              /*   SelectFilter::make('user_id')
                ->label('Subido por')
                ->options(fn () => User::pluck('name', 'id')) // o full_name si lo usas
                ->searchable()
                ->native(false), */
            SelectFilter::make('cliente_id')
                ->label('Cliente')
                ->options(Cliente::pluck('razon_social', 'id'))
                ->searchable()
                ->native(false),       
            TernaryFilter::make('verificado')
                ->label('Verificado')
                ->trueLabel('Solo verificados')
                ->falseLabel('Solo no verificados')
                ->native(false),

            // 📂 Tipo de documento
            SelectFilter::make('tipo_documento_id')
                ->label('Tipo')
                ->options(DocumentoCategoria::pluck('nombre', 'id'))
                ->searchable()
                ->native(false),
              // 🧾 Subtipo de documento
            SelectFilter::make('subtipo_documento_id')
                ->label('Subtipo')
                ->options(DocumentoSubtipo::pluck('nombre', 'id'))
                ->searchable()
                ->native(false),
             // 🧑‍💼 Cliente

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
               // ->native(false),     
            DateRangeFilter::make('created_at')
                ->label('Subido')
                ->placeholder('Rango de fechas a buscar'),  
            DateRangeFilter::make('updated_at')
                ->label('Actualizado')
                ->placeholder('Rango de fechas a buscar'),      
            ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(7)
            ->recordActions([
                EditAction::make(),
                ViewAction::make()
                ->label('Ver'), // Automáticamente usa ViewDocumento

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
            'create' => CreateDocumento::route('/create'),
            'edit' => EditDocumento::route('/{record}/edit'),
            'view' => ViewDocumento::route('/{record}/view'),

        ];
    }

    /* public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\DocumentoResource\Widgets\DocumentoStats::class,
        ];
    } */


}
