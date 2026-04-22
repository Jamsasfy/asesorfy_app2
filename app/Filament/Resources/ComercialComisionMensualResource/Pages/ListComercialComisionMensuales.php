<?php

namespace App\Filament\Resources\ComercialComisionMensualResource\Pages;

use App\Filament\Resources\ComercialComisionMensualResource;
use App\Models\ComercialHistorialObjetivo;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListComercialComisionMensuales extends ListRecords
{
    protected static string $resource = ComercialComisionMensualResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $user        = Auth::user();
        $esComercial = $user->hasRole('comercial') && ! $user->hasRole(['super_admin', 'coordinador']);

        $base = fn () => ComercialHistorialObjetivo::when(
            $esComercial,
            fn ($q) => $q->where('comercial_id', $user->id)
        );

        return [
            'todos' => Tab::make('Todos')
                ->badge($base()->count()),

            'pendientes_aprobar' => Tab::make('Pendientes aprobar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'borrador'))
                ->badge($base()->where('estado', 'borrador')->count())
                ->badgeColor('warning'),

            'pendientes_pagar' => Tab::make('Pendientes pagar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'aprobada'))
                ->badge($base()->where('estado', 'aprobada')->count())
                ->badgeColor('info'),

            'pagadas' => Tab::make('Pagadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'pagada'))
                ->badge($base()->where('estado', 'pagada')->count())
                ->badgeColor('success'),
        ];
    }
}
