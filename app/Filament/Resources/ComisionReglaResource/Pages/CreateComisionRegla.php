<?php

namespace App\Filament\Resources\ComisionReglaResource\Pages;

use App\Filament\Resources\ComisionReglaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateComisionRegla extends CreateRecord
{
    protected static string $resource = ComisionReglaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
