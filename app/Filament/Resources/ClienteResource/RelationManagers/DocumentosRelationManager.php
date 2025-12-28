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
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
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
            ->recordTitleAttribute('nombre')
            ->columns([
                TextColumn::make('user.name')->label('Subido por'),
                TextColumn::make('tipo.nombre')->label('Tipo')->badge(),
                TextColumn::make('subtipo.nombre')->label('Subtipo')->badge(),
                TextColumn::make('ruta')
                    ->label('Archivo')
                    ->url(fn ($record) => Storage::url($record->ruta), true)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn ($record) => $record->nombre),
                IconColumn::make('verificado')->boolean(),
                TextColumn::make('created_at')->label('Subido el')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('tipo_documento_id')->relationship('tipo', 'nombre'),
                SelectFilter::make('subtipo_documento_id')->relationship('subtipo', 'nombre'),
                TernaryFilter::make('verificado'),
                DateRangeFilter::make('created_at')->label('Subido en'),
            ], layout: FiltersLayout::AboveContent)
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
