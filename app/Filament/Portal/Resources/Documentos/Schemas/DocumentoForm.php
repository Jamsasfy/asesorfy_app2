<?php

namespace App\Filament\Portal\Resources\Documentos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('documentable_id')
                    ->numeric(),
                TextInput::make('documentable_type'),
                TextInput::make('nombre')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('cliente_id')
                    ->numeric(),
                TextInput::make('ruta')
                    ->required(),
                TextInput::make('mime_type'),
            /*     TextInput::make('tipo_documento_id')
                    ->required()
                    ->numeric(),
                TextInput::make('subtipo_documento_id')
                    ->required()
                    ->numeric(), */
                Toggle::make('verificado')
                    ->required(),
                Textarea::make('observaciones')
                    ->columnSpanFull(),
            ]);
    }
}
