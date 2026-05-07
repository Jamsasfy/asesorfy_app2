<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\Widgets\LeadStatsOverview;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MisLeads extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'Mi espacio de trabajo';
    protected static ?string $navigationLabel = 'Mis Leads';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.mis-leads';
    protected static ?string $title = 'Mis Leads';

    protected function getHeaderWidgets(): array
    {
        return [
            LeadStatsOverview::make(['soloPropios' => true]),
        ];
    }

    public function table(Table $table): Table
    {
        return LeadResource::table($table)
            ->query(
                fn () => Lead::query()
                    ->where('asignado_id', auth()->id())
                    ->with(['comentarios.user'])
            );
    }
}
