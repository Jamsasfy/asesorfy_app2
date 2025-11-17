<?php

namespace App\Filament\Resources\LeadAutoEmailLogResource\Pages;

use App\Filament\Resources\LeadAutoEmailLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeadAutoEmailLog extends EditRecord
{
    protected static string $resource = LeadAutoEmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
