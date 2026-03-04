<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard de control AsesorFy';

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('startDate')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(now()->startOfMonth()->toDateString()),

                DatePicker::make('endDate')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(now()->toDateString()),
            ])
            ->columns(3);
    }
}
