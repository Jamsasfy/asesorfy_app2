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
                            $nombre = mb_strtolower((string) ($record->tipo?->nombre ?? ''));

                            if ($nombre === 'sin clasificar') {
                                return 'warning';
                            }

                            return $record->tipo?->color ?? 'gray';
                        }),

                    TextColumn::make('subtipo.nombre')
                        ->label('Subtipo')
                        ->badge()
                        ->color(function ($record) {
                            $nombre = mb_strtolower((string) ($record->subtipo?->nombre ?? ''));

                            if ($nombre === 'pendiente de clasificar') {
                                return 'warning';
                            }

                            return $record->subtipo?->color ?? 'gray';
                        }),


                // ✅ Nuevo: Estado (badge)
               TextColumn::make('estado')
                ->label('Estado')
                ->badge()
                ->formatStateUsing(function ($state) {
                    if ($state instanceof DocumentoEstadoEnum) {
                        return $state->label();
                    }

                    return match ((string) $state) {
                        'verificado' => 'Verificado',
                        'rechazado'  => 'Rechazado',
                        default      => 'Pendiente',
                    };
                })
                ->color(function ($state) {
                    if ($state instanceof DocumentoEstadoEnum) {
                        return $state->color();
                    }

                    return match ((string) $state) {
                        'verificado' => 'success',
                        'rechazado'  => 'danger',
                        default      => 'warning',
                    };
                }),

            IconColumn::make('estado_icon')
                ->label('') // o 'Estado'
                ->state(fn ($record) => $record->estado) // 👈 le pasamos el estado real
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


                        /* =========================
                        | HEADER ACTIONS (MODAL SUBIR)
                        ========================= */
                        ->headerActions([
                            CreateAction::make('subir_documento')
                                ->label('Subir documento')
                                ->icon('heroicon-o-document-plus')
                                ->modalHeading('Subir documento')
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
                                                    ->visibility('public')
                                                    ->columnSpanFull(),

                                                Textarea::make('observaciones')
                                                    ->label('Observaciones')
                                                    ->helperText('Opcional. Si quieres, añade una nota para tu asesor.')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2),
                                    ];
                                })
                                ->mutateDataUsing(function (array $data): array {
                                    $user = auth()->user();

                                    if (empty($data['cliente_id'])) {
                                        $data['cliente_id'] = $user->clientes()->pluck('clientes.id')->first();
                                    }

                                    $cliente = $data['cliente_id']
                                        ? \App\Models\Cliente::find($data['cliente_id'])
                                        : null;

                                    // ✅ Asignar tipo/subtipo por defecto (sin clasificar)
                                    $categoria = \App\Models\DocumentoCategoria::query()
                                        ->where('nombre', 'Sin clasificar')
                                        ->first();

                                    $subtipo = $categoria
                                        ? \App\Models\DocumentoSubtipo::query()
                                            ->where('documento_categoria_id', $categoria->id)
                                            ->where('nombre', 'Pendiente de clasificar')
                                            ->first()
                                        : null;

                                    if ($categoria && $subtipo) {
                                        $data['tipo_documento_id'] = $categoria->id;
                                        $data['subtipo_documento_id'] = $subtipo->id;
                                    }

                                    $data['user_id'] = $user->id;
                                    $data['mime_type'] = Storage::disk('public')->mimeType($data['ruta']);

                                    $data['estado'] = DocumentoEstadoEnum::PENDIENTE->value;
                                    $data['verificado'] = false;

                                    if ($cliente) {
                                        $data['documentable_type'] = \App\Models\Cliente::class;
                                        $data['documentable_id'] = $cliente->id;
                                    }

                                    // ✅ Nombre automático (sin depender de selects del cliente)
                                   if (empty($data['nombre'])) {
                                        $random = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
                                        $extension = pathinfo($data['ruta'], PATHINFO_EXTENSION);

                                        $data['nombre'] = 'cliente_documento_' . $random . ($extension ? ".{$extension}" : '');
                                    }


                                    return $data;
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
