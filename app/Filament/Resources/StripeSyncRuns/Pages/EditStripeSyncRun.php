<?php

namespace App\Filament\Resources\StripeSyncRuns\Pages;

use App\Filament\Resources\StripeSyncRuns\StripeSyncRunResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStripeSyncRun extends EditRecord
{
    protected static string $resource = StripeSyncRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
