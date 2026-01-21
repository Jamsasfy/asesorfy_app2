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
use Livewire\Component;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;






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

        // ✅ Icono del estado (igual que portal)
    IconColumn::make('estado_icon')
        ->label('')
        ->state(fn ($record) => $record->estado)
        ->icon(function ($state) {
            if ($state instanceof DocumentoEstadoEnum) {
                return match ($state) {
                    DocumentoEstadoEnum::VERIFICADO => 'heroicon-m-check-circle',
                    DocumentoEstadoEnum::PENDIENTE  => 'heroicon-m-exclamation-triangle',
                    DocumentoEstadoEnum::RECHAZADO  => 'heroicon-m-x-circle',
                };
            }

            return match ((string) $state) {
                'verificado' => 'heroicon-m-check-circle',
                'pendiente'  => 'heroicon-m-exclamation-triangle',
                'rechazado'  => 'heroicon-m-x-circle',
                default      => 'heroicon-m-question-mark-circle',
            };
        })
        ->color(function ($state) {
            if ($state instanceof DocumentoEstadoEnum) {
                return match ($state) {
                    DocumentoEstadoEnum::VERIFICADO => 'success',
                    DocumentoEstadoEnum::PENDIENTE  => 'warning',
                    DocumentoEstadoEnum::RECHAZADO  => 'danger',
                };
            }

            return match ((string) $state) {
                'verificado' => 'success',
                'pendiente'  => 'warning',
                'rechazado'  => 'danger',
                default      => 'gray',
            };
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
            DocumentoEstadoEnum::PENDIENTE->value  => DocumentoEstadoEnum::PENDIENTE->label(),
            DocumentoEstadoEnum::VERIFICADO->value => DocumentoEstadoEnum::VERIFICADO->label(),
            DocumentoEstadoEnum::RECHAZADO->value  => DocumentoEstadoEnum::RECHAZADO->label(),
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
