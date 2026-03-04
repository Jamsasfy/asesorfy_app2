<?php

namespace App\Filament\Portal\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class SelectorEmpresaWidget extends Widget
{
    protected string $view = 'filament.portal.widgets.selector-empresa';

    // No ocupa espacio en el grid de widgets del dashboard
    protected static bool $isLazy = false;
    protected int | string | array $columnSpan = 'full';

    // Solo se muestra si el usuario tiene más de un cliente
    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->clientes()->count() > 1;
    }

    public function getClienteActivo()
    {
        $id = session('cliente_activo_id');
        if (!$id) return null;

        return Auth::user()->clientes()->find($id);
    }

    public function getClientes()
    {
        return Auth::user()->clientes()->get();
    }

    public function cambiar(int $clienteId): void
    {
        $existe = Auth::user()->clientes()->where('clientes.id', $clienteId)->exists();

        if (!$existe) return;

        session(['cliente_activo_id' => $clienteId]);

        // Redirigir al dashboard para recargar todo con el nuevo cliente
        $this->redirect('/portal');
    }
}