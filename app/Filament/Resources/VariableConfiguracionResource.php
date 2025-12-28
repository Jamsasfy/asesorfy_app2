<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VariableConfiguracionResource\Pages\ListVariableConfiguracions;
use App\Filament\Resources\VariableConfiguracionResource\Pages\CreateVariableConfiguracion;
use App\Filament\Resources\VariableConfiguracionResource\Pages\EditVariableConfiguracion;
use App\Filament\Resources\VariableConfiguracionResource\Pages;
use App\Models\VariableConfiguracion;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VariableConfiguracionResource extends Resource
{
    protected static ?string $model = VariableConfiguracion::class;

    // Icono que aparecerá en la navegación lateral de Filament
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    // Etiqueta singular en la interfaz
    protected static ?string $modelLabel = 'Variable de Configuración';
    // Etiqueta plural en la interfaz
    protected static ?string $pluralModelLabel = 'Variables de Configuración';
    // Grupo de navegación (opcional, para organizar en el menú)
    protected static string | \UnitEnum | null $navigationGroup = 'Configuración del Negocio';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre_variable')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true) // Asegura que el nombre sea único, excepto para el registro actual al editar
                    ->label('Nombre de la Variable')
                    ->helperText('Nombre único que usarás en tu código para acceder a esta variable (ej. IVA_general, stripe_secret_key).'),

                Textarea::make('valor_variable')
                    ->required()
                    ->label('Valor de la Variable')
                    ->helperText('Contenido de la variable. Si "Es un Secreto" está activado, este valor se guardará cifrado en la base de datos.'),

                Select::make('tipo_dato')
                    ->options([
                        'cadena' => 'Cadena de Texto',
                        'numero_entero' => 'Número Entero',
                        'numero_decimal' => 'Número Decimal',
                        'booleano' => 'Booleano (Sí/No)',
                    ])
                    ->required()
                    ->label('Tipo de Dato')
                    ->helperText('Indica cómo debe interpretar tu aplicación el valor (texto, número entero, decimal, verdadero/falso).'),

                Textarea::make('descripcion')
                    ->maxLength(65535) // Longitud máxima para un campo TEXT en MySQL
                    ->nullable()
                    ->label('Descripción')
                    ->helperText('Una breve descripción del propósito de esta variable para referencia futura.'),

                Toggle::make('es_secreto')
                    ->label('¿Es un Secreto?')
                    ->helperText('Activar si esta variable contiene información sensible (ej. claves API, contraseñas) que debe ser cifrada para su almacenamiento.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_variable')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre de la Variable'),
                TextColumn::make('tipo_dato')
                    ->label('Tipo de Dato'),
                IconColumn::make('es_secreto')
                    ->boolean() // Muestra un icono de checkmark o cruz
                    ->label('Secreto'),
                TextColumn::make('descripcion')
                    ->limit(50) // Limita la longitud en la tabla para no ocupar demasiado espacio
                    ->tooltip(fn (VariableConfiguracion $record): ?string => $record->descripcion ?? null) // <-- ¡Corregido!
                    ->label('Descripción'),
                TextColumn::make('updated_at')
                    ->dateTime() // Formatea la fecha y hora
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true) // Oculta esta columna por defecto, se puede mostrar
                    ->label('Última Actualización'),
            ])
            ->filters([
                // Aquí puedes añadir filtros si los necesitas más adelante
            ])
            ->recordActions([
                EditAction::make(), // Acción para editar un registro
                DeleteAction::make(), // Acción para eliminar un registro
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(), // Acción para eliminar múltiples registros
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Aquí puedes definir relaciones si tuvieras más tablas relacionadas
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariableConfiguracions::route('/'),
            'create' => CreateVariableConfiguracion::route('/create'),
            'edit' => EditVariableConfiguracion::route('/{record}/edit'),
        ];
    }
}