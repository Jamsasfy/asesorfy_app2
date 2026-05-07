<?php

namespace App\Filament\Resources\LeadResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\Widgets\LeadStatsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;






class ListLeads extends ListRecords
{
   
    protected static string $resource = LeadResource::class;
    


    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            return [];
        }

        return [
            LeadStatsOverview::class,
        ];
    }



}
