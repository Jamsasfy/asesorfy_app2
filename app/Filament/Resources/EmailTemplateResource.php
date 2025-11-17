<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

// ✅ v3: estos dos imports son CLAVE
use Filament\Forms\Form;
use Filament\Tables\Table;

// Usa los namespaces “padre” y luego referéncialos como Forms\... y Tables\...
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Tables;

class EmailTemplateResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Comunicación';
    protected static ?string $modelLabel = 'Plantilla de Email';
    protected static ?string $pluralModelLabel = 'Plantillas de Email';
    protected static ?string $navigationLabel = 'Plantillas Email';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información básica')
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre interno')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('Identificador único')
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->helperText('Ej: propuesta_enviada, convertido, esperando_informacion...'),

                        Forms\Components\TextInput::make('asunto')
                            ->label('Asunto del email')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('activo')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contenido del correo')
                    ->description('Puedes usar variables como {{ $lead->nombre }} o {{ $estado_label }}')
                    ->schema([
                       Textarea::make('contenido_html')
    ->label('Contenido del Email (HTML / Blade)')
    ->rows(14)
    ->helperText('Puedes usar variables Blade como {{ $lead->nombre }}, {{ config("app.name") }}, etc.')
    ->required()
    ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->sortable(),
                Tables\Columns\TextColumn::make('asunto')->label('Asunto')->wrap(),
                Tables\Columns\IconColumn::make('activo')->boolean()->label('Activo'),
                Tables\Columns\TextColumn::make('updated_at')->label('Última modificación')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
