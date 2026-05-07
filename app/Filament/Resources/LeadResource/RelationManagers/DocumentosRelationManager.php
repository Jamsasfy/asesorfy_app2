<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Enums\DocumentoEstadoEnum;
use App\Models\DocumentoSubtipo;
use App\Models\Lead;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentosPolimorficos';
    protected static ?string $title = 'Documentos';

    public function isReadOnly(): bool
    {
        return false;
    }

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Subido por')
                    ->formatStateUsing(fn ($record) => $record->user?->full_name ?? $record->user?->name ?? 'Usuario desconocido'),

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
                    ->limit(35)
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->tooltip(fn ($record) => $record->verificado ? 'Marcar como NO verificado' : 'Marcar como verificado')
                    ->visible(fn () => true),

                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tipo_documento_id')->relationship('tipo', 'nombre')->label('Tipo'),
                SelectFilter::make('subtipo_documento_id')->relationship('subtipo', 'nombre')->label('Subtipo'),
                TernaryFilter::make('verificado')->label('Verificado'),
                DateRangeFilter::make('created_at')->label('Subido en'),
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([
                CreateAction::make()
                    ->label('Subir documento')
                    ->mutateDataUsing(function (array $data): array {

                        $user = Auth::user();
                        /** @var Lead $lead */
                        $lead = $this->getOwnerRecord();

                        $data['user_id'] = $user->id;
                        $data['mime_type'] = Storage::disk('public')->mimeType($data['ruta']);
                        $data['verificado'] = (bool) ($user->trabajador ?? false);

                        // ✅ si el lead ya está convertido y tiene cliente_id, lo asociamos; si no, null
                        $data['cliente_id'] = $lead->cliente_id ?? null;

                        // ✅ generar nombre si no lo escribió
                        if (empty($data['nombre'])) {
                            $tipo = Str::slug(optional($lead->documentosPolimorficos()->getModel()->tipo()->find($data['tipo_documento_id']))?->nombre ?? 'tipo');
                            $subtipo = Str::slug(optional(DocumentoSubtipo::find($data['subtipo_documento_id']))?->nombre ?? 'subtipo');

                            $random = Str::lower(Str::random(6));
                            $extension = pathinfo($data['ruta'], PATHINFO_EXTENSION);

                            // prefijo lead para distinguirlos
                            $data['nombre'] = "lead_{$tipo}_{$subtipo}_{$random}.{$extension}";
                        }

                        return $data;
                    })
                    ->after(function ($record, $livewire) {
                        // ✅ refresco v4
                        $record->refresh();
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->url(fn ($record) => route('filament.admin.resources.documentos.view', ['record' => $record->id]))
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
