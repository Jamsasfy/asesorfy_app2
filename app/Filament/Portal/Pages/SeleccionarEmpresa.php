<?php

namespace App\Filament\Portal\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SeleccionarEmpresa extends Page
{
    protected string $view = 'filament.portal.pages.seleccionar-empresa';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Selecciona tu empresa';

    protected static ?string $slug = 'seleccionar-empresa';

    public function mount()
    {
        $user = Auth::user();
        $clientes = $user->clientes()->get();

        // Cambio rápido desde el dropdown del dashboard
        $cambiarId = request()->query('cambiar');
        if ($cambiarId) {
            $existe = $clientes->contains('id', (int) $cambiarId);
            if ($existe) {
                session(['cliente_activo_id' => (int) $cambiarId]);
            }
            return redirect('/portal');
        }

        if ($clientes->isEmpty()) {
            return redirect('/portal');
        }

        if ($clientes->count() === 1) {
            session(['cliente_activo_id' => $clientes->first()->id]);
            return redirect('/portal');
        }

        $clienteActivoId = session('cliente_activo_id');
        if ($clienteActivoId && $clientes->contains('id', $clienteActivoId)) {
            return redirect('/portal');
        }
    }

    public function seleccionar(int $clienteId)
    {
        $user = Auth::user();

        $existe = $user->clientes()->where('clientes.id', $clienteId)->exists();

        if (!$existe) {
            $this->addError('seleccion', 'No tienes acceso a esa empresa.');
            return;
        }

        session(['cliente_activo_id' => $clienteId]);

        return redirect('/portal');
    }

    public function getClientes()
    {
        return Auth::user()->clientes()->get();
    }
}