<?php

namespace App\Filament\Resources\ProyectoResource\RelationManagers;

use App\Enums\DocumentoEstadoEnum;
use App\Models\DocumentoSubtipo;
use App\Models\Proyecto;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;   

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentosPolimorficos';
    protected static ?string $title = 'Documentos de este proyecto';

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

            Textarea::make('observaciones')
                ->label('Observaciones')
                ->columnSpanFull(),
        ]);
    }

    /* ===========================
     * TABLE
     * =========================== */
    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Subido por')
                    ->formatStateUsing(fn ($record) =>
                        $record->user->full_name
                        ?? $record->user->name
                        ?? 'Usuario desconocido'
                    ),

                TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($record) => $record->tipo->color ?? 'gray'),

                TextColumn::make('subtipo.nombre')
                    ->label('Subtipo')
                    ->badge()
                    ->color('info'),

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
                        if (! (auth()->user()?->can('Verificar:Documento') ?? false)) {
                            Notification::make()
                                ->title('No tienes permiso para verificar documentos.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $estadoActual = $record->estado;

                        // Proteger estados especiales — no permitir toggle directo
                        if (in_array($estadoActual, [
                            DocumentoEstadoEnum::RECHAZADO,
                            DocumentoEstadoEnum::NECESITA_ACLARACION,
                        ], true)) {
                            Notification::make()
                                ->title('No se puede verificar directamente')
                                ->body('Este documento está en estado "' . $estadoActual->label() . '". Ábrelo para gestionarlo correctamente.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Toggle entre VERIFICADO y PENDIENTE
                        $nuevoEstado = $estadoActual === DocumentoEstadoEnum::VERIFICADO
                            ? DocumentoEstadoEnum::PENDIENTE
                            : DocumentoEstadoEnum::VERIFICADO;

                        $record->update(['estado' => $nuevoEstado]);

                        Notification::make()
                            ->title($nuevoEstado === DocumentoEstadoEnum::VERIFICADO
                                ? 'Documento verificado'
                                : 'Verificación retirada')
                            ->success()
                            ->send();

                        $record->refresh();
                        $livewire->dispatch('$refresh');
                    })
                    ->tooltip(fn ($record) => $record->verificado
                        ? 'Marcar como NO verificado'
                        : 'Marcar como verificado'),

                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        /** @var Proyecto $proyecto */
                        $proyecto = $this->getOwnerRecord();
                        $user = Auth::user();

                        $data['user_id'] = $user->id;
                        $data['cliente_id'] = $proyecto->cliente_id; // ✅ CLAVE
                        $data['mime_type'] = Storage::disk('public')->mimeType($data['ruta']);
                        $data['verificado'] = (bool) ($user->trabajador ?? false);

                        if (empty($data['nombre'])) {
                            $subtipo = Str::slug(
                                DocumentoSubtipo::find($data['subtipo_documento_id'])?->nombre ?? 'documento'
                            );

                            $extension = pathinfo($data['ruta'], PATHINFO_EXTENSION);
                            $random = Str::lower(Str::random(6));

                            $data['nombre'] = "{$subtipo}_{$random}.{$extension}";
                        }

                        return $data;
                    })
                    ->after(function ($record, $livewire) {
                        $proyecto = $record->documentable;

                        if ($proyecto instanceof Proyecto) {
                            $proyecto->comentarios()->create([
                                'user_id' => auth()->id(),
                                'contenido' => "📎 Se ha subido el documento {$record->nombre}.",
                            ]);
                        }

                        $record->refresh();
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) =>
                        route('filament.admin.resources.documentos.view', ['record' => $record->id])
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
