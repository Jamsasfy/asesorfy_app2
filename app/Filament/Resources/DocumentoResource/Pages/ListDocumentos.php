<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\DocumentoResource;
use App\Filament\Resources\DocumentoResource\Widgets\DocumentoStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder; // ¡Importante!
use Illuminate\Support\Facades\Auth;  

class ListDocumentos extends ListRecords
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
           // CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DocumentoStats::class,
        ];
    }

  // ESTE ES EL MÉTODO CLAVE PARA FILTRAR LA TABLA PRINCIPAL
   


}
