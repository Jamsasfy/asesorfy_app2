<?php

namespace App\Filament\Resources\StripeSyncRuns\Pages;

use App\Filament\Resources\StripeSyncRuns\StripeSyncRunResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStripeSyncRun extends ViewRecord
{
    protected static string $resource = StripeSyncRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
          //  EditAction::make(),
        ];
    }

       // ✅ refresco automático del detalle (Filament v4)
    public function getPollingInterval(): ?string
    {
        return '2s';
    }
}
