<?php

namespace App\Filament\Resources\StripeSyncRuns\Pages;

use App\Filament\Resources\StripeSyncRuns\StripeSyncRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStripeSyncRuns extends ListRecords
{
    protected static string $resource = StripeSyncRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
           // CreateAction::make(),
        ];
    }

       // ✅ refresco automático del listado (Filament v4)
    public function getPollingInterval(): ?string
    {
        return '2s';
    }
}
