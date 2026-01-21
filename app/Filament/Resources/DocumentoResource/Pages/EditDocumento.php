<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use App\Filament\Resources\DocumentoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumento extends EditRecord
{
    protected static string $resource = DocumentoResource::class;

    /**
     * Conserva chain/cliente/seen cuando navegas entre view/edit.
     */
    protected function getChainQueryString(): string
    {
        $qs = request()->only(['chain', 'cliente', 'seen']);
        $qs = array_filter($qs, fn ($v) => $v !== null && $v !== '');
        return $qs ? ('?' . http_build_query($qs)) : '';
    }

    /**
     * Si vienes desde RelationManager del cliente, vuelve a /clientes/{id}?relation=1.
     * Si no, vuelve al index normal de Documentos.
     */
    protected function getReturnUrl(): string
    {
        $clienteId = (int) request('cliente');

        if ($clienteId > 0) {
            return route('filament.admin.resources.clientes.view', ['record' => $clienteId]) . '?relation=1';
        }

        return route('filament.admin.resources.documentos.index');
    }

    /**
     * Después de guardar -> volver a la vista del documento manteniendo querystring.
     */
    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('view', ['record' => $this->record]) . $this->getChainQueryString();
    }

    /**
     * Si cancelas -> volver a la vista del documento manteniendo querystring.
     */
    protected function getCancelUrl(): string
    {
        return static::$resource::getUrl('view', ['record' => $this->record]) . $this->getChainQueryString();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->url(fn () => static::$resource::getUrl('view', ['record' => $this->record]) . $this->getChainQueryString()),

            DeleteAction::make()
                ->successRedirectUrl(fn () => $this->getReturnUrl()),
        ];
    }
}
