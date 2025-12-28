<?php

namespace App\Filament\Resources\ProcedenciaResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ProcedenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcedencias extends ListRecords
{
    protected static string $resource = ProcedenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
