<?php

namespace App\Filament\Resources\LeadAutoEmailLogResource\Pages;

use App\Filament\Resources\LeadAutoEmailLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;

class ViewLeadAutoEmailLog extends ViewRecord
{
    protected static string $resource = LeadAutoEmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Volver')
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
                ->url(self::getResource()::getUrl('index')),
        ];
    }
}
