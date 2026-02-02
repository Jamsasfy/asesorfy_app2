<?php

namespace App\Filament\Portal\Resources\Documentos\Tables;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Portal\Resources\Documentos\DocumentoResource;
use App\Models\Cliente;
use App\Models\DocumentoCategoria;
use App\Models\DocumentoSubtipo;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;




class DocumentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->poll(10)
             ->deferFilters(false)
            ->modifyQueryUsing(fn ($query) => $query->where('hidden_in_portal', false))
            ->columns([
                // 🔁 Subido por (icono tipo “llamadas”)
                IconColumn::make('user_id')
                    ->label('Subido por')
                    ->icon(function ($state) {
                        $isMine = (int) $state === (int) auth()->id();

                        return $isMine
                            ? 'heroicon-o-arrow-up-right'
                            : 'heroicon-o-arrow-down-left';
                    })
                    ->color(function ($state) {
                        $isMine = (int) $state === (int) auth()->id();
                        return $isMine ? 'success' : 'primary';
                    })
                    ->tooltip(function ($state) {
                        $isMine = (int) $state === (int) auth()->id();
                        return $isMine ? 'Subido por mí' : 'Subido por mi asesor';
                    })
                    ->alignCenter(),

                // 📎 Tipo archivo (icono)
                IconColumn::make('mime_type')
                    ->label('')
                    ->icon(fn ($record) => self::mimeIcon($record))
                    ->tooltip(fn ($record) => $record->mime_type ?? 'Desconocido')
                    ->color(fn ($record) => self::mimeColor($record))
                    ->alignCenter(),

                    TextColumn::make('archivo')
                    ->label('Archivo')
                    ->state(fn ($record) => filled($record->ruta) ? ($record->nombre ?? 'Archivo') : 'Purgado')
                    ->badge(fn ($record) => blank($record->ruta))
                    ->color(fn ($record) => blank($record->ruta) ? 'danger' : null),
            

                  TextColumn::make('tipo.nombre')
                            ->label('Tipo')
                            ->badge()
                            ->color(function ($record) {
                                $tipo = $record->tipo?->nombre;

                                if (blank($tipo)) {
                                    return 'gray'; // no hay tipo aún
                                }

                                return mb_strtolower((string) $tipo) === 'sin clasificar'
                                    ? 'gray'
                                    : 'info';
                            }),
                                TextColumn::make('subtipo.nombre')
                                    ->label('Subtipo')
                                    ->badge()
                                    ->color(function ($record) {
                                        $subtipo = $record->subtipo?->nombre;

                                        if (blank($subtipo)) {
                                            return 'gray'; // no hay subtipo aún
                                        }

                                        return mb_strtolower((string) $subtipo) === 'pendiente de clasificar'
                                            ? 'gray'
                                            : 'info';
                                    }),



                // ✅ Nuevo: Estado (badge)
            TextColumn::make('estado')
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
            DocumentoEstadoEnum::PENDIENTE           => 'gray',    // 👈 portal: no molestar
            DocumentoEstadoEnum::NECESITA_ACLARACION => 'warning', // 👈 lo importante
            DocumentoEstadoEnum::RECHAZADO           => 'danger',
            DocumentoEstadoEnum::ARCHIVADO           => 'gray',
            default                                  => 'gray',
        };
    }),


         IconColumn::make('estado_icon')
    ->label('')
    ->state(fn ($record) => $record) // 👈 necesitamos el record
    ->icon(function ($record) {
        $estado = $record->estado instanceof DocumentoEstadoEnum
            ? $record->estado
            : DocumentoEstadoEnum::tryFrom((string) $record->estado);

        return match ($estado) {
            DocumentoEstadoEnum::VERIFICADO          => 'heroicon-m-check-circle',
            DocumentoEstadoEnum::RECHAZADO           => 'heroicon-m-x-circle',
            DocumentoEstadoEnum::NECESITA_ACLARACION => 'heroicon-m-question-mark-circle',
            DocumentoEstadoEnum::ARCHIVADO           => 'heroicon-m-archive-box',
            DocumentoEstadoEnum::PENDIENTE           => filled($record->aclaracion_respondida_at)
                ? 'heroicon-m-arrow-path'  // ✅ volvió a revisión
                : 'heroicon-m-clock',      // normal
            default => 'heroicon-m-question-mark-circle',
        };
    })
    ->color(function ($record) {
        $estado = $record->estado instanceof DocumentoEstadoEnum
            ? $record->estado
            : DocumentoEstadoEnum::tryFrom((string) $record->estado);

        return match ($estado) {
            DocumentoEstadoEnum::VERIFICADO          => 'success',
            DocumentoEstadoEnum::RECHAZADO           => 'danger',
            DocumentoEstadoEnum::NECESITA_ACLARACION => 'warning', // lo importante
            DocumentoEstadoEnum::ARCHIVADO           => 'gray',
            DocumentoEstadoEnum::PENDIENTE           => filled($record->aclaracion_respondida_at)
                ? 'info'  // ✅ azul: “recibido, revisando”
                : 'gray',
            default => 'gray',
        };
    })
    ->tooltip(function ($record) {
        $estado = $record->estado instanceof DocumentoEstadoEnum
            ? $record->estado
            : DocumentoEstadoEnum::tryFrom((string) $record->estado);

        if ($estado === DocumentoEstadoEnum::PENDIENTE && filled($record->aclaracion_respondida_at)) {
            return 'Hemos recibido tu respuesta. Está de nuevo en revisión.';
        }

        return $estado?->label() ?? 'En revisión';
    })
    ->alignCenter(),




                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')

            ->filters([
                        // ✅ Tipo + Subtipo REACTIVO (Subtipo aparece solo si hay Tipo)
                        Filter::make('tipo_y_subtipo')
                            ->label('Tipo / Subtipo')
                            ->form([
                                Select::make('tipo_documento_id')
                                    ->label('Tipo')
                                    ->options(fn () => DocumentoCategoria::query()
                                        ->orderBy('nombre')
                                        ->pluck('nombre', 'id')
                                        ->toArray()
                                    )
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

                        // ✅ Estado
                        SelectFilter::make('estado')
                            ->label('Estado')
                            ->options([
                                DocumentoEstadoEnum::PENDIENTE->value  => DocumentoEstadoEnum::PENDIENTE->label(),
                                DocumentoEstadoEnum::VERIFICADO->value => DocumentoEstadoEnum::VERIFICADO->label(),
                                DocumentoEstadoEnum::RECHAZADO->value  => DocumentoEstadoEnum::RECHAZADO->label(),
                            ])
                            ->native(false)
                            ->query(function (Builder $query, array $data): Builder {
                                $value = $data['value'] ?? null;

                                if (! filled($value)) {
                                    return $query;
                                }

                                return $query->where('estado', $value);
                            }),

                        Filter::make('desde')
                            ->form([
                                DatePicker::make('desde')->label('Desde'),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                if (! filled($data['desde'] ?? null)) {
                                    return $query;
                                }

                                return $query->whereDate('created_at', '>=', $data['desde']);
                            }),

                        Filter::make('hasta')
                            ->form([
                                DatePicker::make('hasta')->label('Hasta'),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                if (! filled($data['hasta'] ?? null)) {
                                    return $query;
                                }

                                return $query->whereDate('created_at', '<=', $data['hasta']);
                            }),

                        SelectFilter::make('subido_por')
                            ->label('Subido por')
                            ->options([
                                'all' => 'Todos',
                                'me' => 'Subidos por mí',
                                'advisor' => 'Subidos por mi asesor',
                            ])
                            ->default('all')
                            ->native(false)
                            ->query(function (Builder $query, array $data): Builder {
                                $value = $data['value'] ?? 'all';
                                $me = (int) auth()->id();

                                return match ($value) {
                                    'me' => $query->where('user_id', $me),
                                    'advisor' => $query->where('user_id', '!=', $me),
                                    default => $query,
                                };
                            }),
                    ], layout: FiltersLayout::AboveContent)
                 
                    ->emptyStateHeading('Aún no tienes documentos')
                    ->emptyStateDescription('Sube tu primer documento para que tu asesor pueda revisarlo.')
                    ->emptyStateIcon('heroicon-o-document-plus')


                       ->headerActions([
    Action::make('subir_documentos')
        ->label('Subir documentos')
        ->icon('heroicon-o-document-plus')
        ->modalHeading('Subir documentos')
        ->closeModalByClickingAway(false)
        ->closeModalByEscaping(false)
        ->modalWidth('3xl')
        ->form(function (): array {
            $user = auth()->user();
            $clientes = $user->clientes()->select('clientes.id', 'razon_social')->get();
            $tieneVarios = $clientes->count() > 1;

            return [
                Section::make()
                    ->schema([
                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->options($clientes->pluck('razon_social', 'id')->toArray())
                            ->required()
                            ->native(false)
                            ->visible($tieneVarios)
                            ->columnSpanFull(),

                        FileUpload::make('rutas')
                            ->label('Archivos')
                            ->disk('public')
                            ->directory('documentos')
                            ->maxSize(51200) // 50MB
                            ->validationAttribute('Archivos')
                            ->validationMessages([
                                'uploaded' => 'El archivo no se pudo subir. Asegúrate de que no supera el tamaño máximo permitido.',
                                'max'      => 'El archivo supera el tamaño máximo permitido (50 MB).',
                            ])
                            ->required()
                            ->multiple()
                            ->previewable(false) // ✅ fuera miniaturas/previews (compacto y seguro)
                            ->maxFiles(20)       // ✅ evita modal infinito por cantidad
                            ->helperText('Puedes subir varios a la vez (máx. 20 por tanda). Si tienes más, repite el proceso.')
                            ->hint(function () {
                                $maxKb = 51200; // tu maxSize() (KB)

                                $toBytes = function (?string $val): int {
                                    $val = trim((string) $val);
                                    if ($val === '') return 0;
                                    $last = strtolower($val[strlen($val) - 1]);
                                    $num = (int) $val;

                                    return match ($last) {
                                        'g' => $num * 1024 * 1024 * 1024,
                                        'm' => $num * 1024 * 1024,
                                        'k' => $num * 1024,
                                        default => (int) $val,
                                    };
                                };

                                $phpUpload = $toBytes(ini_get('upload_max_filesize'));
                                $phpPost   = $toBytes(ini_get('post_max_size'));

                                $phpLimitBytes = 0;
                                if ($phpUpload > 0 && $phpPost > 0) $phpLimitBytes = min($phpUpload, $phpPost);
                                elseif ($phpUpload > 0) $phpLimitBytes = $phpUpload;
                                elseif ($phpPost > 0) $phpLimitBytes = $phpPost;

                                $filamentBytes = $maxKb * 1024;
                                $effectiveBytes = $phpLimitBytes > 0 ? min($phpLimitBytes, $filamentBytes) : $filamentBytes;

                                $effectiveMb = max(1, (int) floor($effectiveBytes / 1024 / 1024));

                                return "Tamaño máximo por archivo: {$effectiveMb} MB";
                            })
                            ->uploadingMessage('Subiendo archivos… espera a que termine para enviar')
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
                            ->visibility('public')
                            ->columnSpanFull(),

                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->helperText('Opcional. Si quieres, añade una nota para tu asesor (se copia en cada documento).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ];
        })
        ->action(function (array $data): void {
            $user = auth()->user();

            $clienteId = (int) ($data['cliente_id'] ?? 0);
            if ($clienteId <= 0) {
                $clienteId = (int) $user->clientes()->pluck('clientes.id')->first();
            }

            $cliente = $clienteId ? \App\Models\Cliente::find($clienteId) : null;

            // ✅ Tipo/Subtipo por defecto
            $categoria = \App\Models\DocumentoCategoria::query()
                ->where('nombre', 'Sin clasificar')
                ->first();

            $subtipo = $categoria
                ? \App\Models\DocumentoSubtipo::query()
                    ->where('documento_categoria_id', $categoria->id)
                    ->where('nombre', 'Pendiente de clasificar')
                    ->first()
                : null;

            $rutas = (array) ($data['rutas'] ?? []);
            $observaciones = $data['observaciones'] ?? null;

            foreach ($rutas as $ruta) {
                $mime = filled($ruta) ? Storage::disk('public')->mimeType($ruta) : null;

                // ✅ Nombre automático por archivo
                $random = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
                $extension = pathinfo((string) $ruta, PATHINFO_EXTENSION);
                $nombre = 'cliente_documento_' . $random . ($extension ? ".{$extension}" : '');

                $payload = [
                    'cliente_id'             => $clienteId,
                    'user_id'                => $user->id,
                    'ruta'                   => $ruta,
                    'mime_type'              => $mime,
                    'observaciones'          => $observaciones,
                    'observaciones_internas' => null,
                    'estado'                 => \App\Enums\DocumentoEstadoEnum::PENDIENTE->value,
                    'verificado'             => false,
                    'nombre'                 => $nombre,
                ];

                if ($categoria && $subtipo) {
                    $payload['tipo_documento_id'] = $categoria->id;
                    $payload['subtipo_documento_id'] = $subtipo->id;
                }

                if ($cliente) {
                    $payload['documentable_type'] = \App\Models\Cliente::class;
                    $payload['documentable_id'] = $cliente->id;
                }

                \App\Models\Documento::create($payload);
            }
        }),
])



            ->recordActions([
               Action::make('ver')
                ->label('Ver')
                ->icon('heroicon-o-eye')
                ->url(function ($record, Component $livewire) {
                    // Filament guarda la pestaña en $activeTab (aunque en URL sea ?tab=...)
                    $tab = $livewire->activeTab ?? null;

                    $url = DocumentoResource::getUrl('view', ['record' => $record]);

                    // Si quieres que "Todos" no ensucie la URL:
                    if (filled($tab) && $tab !== 'todos') {
                        $url .= '?tab=' . $tab;
                    }

                    return $url;
                })
                ->link(),


                Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        $disk = Storage::disk('public');
                        $path = $disk->path($record->ruta);

                        $filename = $record->nombre;
                        if ($filename && ! str_contains($filename, '.')) {
                            $ext = pathinfo((string) $record->ruta, PATHINFO_EXTENSION);
                            if ($ext) {
                                $filename .= '.' . $ext;
                            }
                        }

                        return response()->download($path, $filename ?: basename($path));
                    })
                    ->visible(fn ($record) => filled($record->ruta)),
            ]);
    }

    private static function mimeIcon($record): string
    {
        $mime = (string) ($record->mime_type ?? '');
        $ext = strtolower(pathinfo((string) ($record->ruta ?? ''), PATHINFO_EXTENSION));

        if ($mime === 'application/pdf' || $ext === 'pdf') return 'icon-pdf';

        if (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return 'heroicon-o-photo';
        }

        if (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true)) return 'icon-doc';

        if (in_array($mime, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], true)) return 'icon-excel';

        return 'heroicon-o-question-mark-circle';
    }

    private static function mimeColor($record): string
    {
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
    }
}
