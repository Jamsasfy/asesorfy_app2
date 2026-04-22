<?php

namespace App\Filament\Resources\ComisionReglaResource\Pages;

use App\Filament\Resources\ComisionReglaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComisionRegla extends EditRecord
{
    protected static string $resource = ComisionReglaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
