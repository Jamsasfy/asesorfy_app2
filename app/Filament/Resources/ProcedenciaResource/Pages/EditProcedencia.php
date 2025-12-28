<?php

namespace App\Filament\Resources\ProcedenciaResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ProcedenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcedencia extends EditRecord
{
    protected static string $resource = ProcedenciaResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
