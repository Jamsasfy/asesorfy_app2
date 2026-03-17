<?php

namespace App\Filament\Resources\NotificacionPortalResource\Pages;

use App\Filament\Resources\NotificacionPortalResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListNotificacionesPortal extends ListRecords
{
    protected static string $resource = NotificacionPortalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
