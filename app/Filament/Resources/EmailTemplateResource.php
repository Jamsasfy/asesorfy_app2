<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Filament\Resources\EmailTemplateResource\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Resources\Resource;
use Filament\Tables\Table;

// Usa los namespaces “padre” y luego referéncialos como Forms\... y Tables\...
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Tables;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static string | \UnitEnum | null $navigationGroup = 'Comunicación';
    protected static ?string $modelLabel = 'Plantilla de Email';
    protected static ?string $pluralModelLabel = 'Plantillas de Email';
    protected static ?string $navigationLabel = 'Plantillas Email';



    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información básica')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre interno')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Identificador único')
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->helperText('Ej: propuesta_enviada, convertido, esperando_informacion...'),

                        TextInput::make('asunto')
                            ->label('Asunto del email')
                            ->required()
                            ->maxLength(255),

                        Toggle::make('activo')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Contenido del correo')
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
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->sortable(),
                TextColumn::make('asunto')->label('Asunto')->wrap(),
                IconColumn::make('activo')->boolean()->label('Activo'),
                TextColumn::make('updated_at')->label('Última modificación')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
