<?php

namespace App\Filament\Resources\LeadAutoEmailLogResource\Pages;

use App\Filament\Resources\LeadAutoEmailLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeadAutoEmailLogs extends ListRecords
{
    protected static string $resource = LeadAutoEmailLogResource::class;

    protected function getHeaderActions(): array
    {
        // No queremos botón de crear ni nada, esto es solo log
        return [
            // Actions\CreateAction::make(), // lo dejamos comentado
        ];
    }
}
