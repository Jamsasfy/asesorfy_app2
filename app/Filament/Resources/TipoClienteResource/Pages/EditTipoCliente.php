<?php

namespace App\Filament\Resources\TipoClienteResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\TipoClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoCliente extends EditRecord
{
    protected static string $resource = TipoClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
