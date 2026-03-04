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

    public function mount(): void
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
                $this->redirect('/portal');
                return;
            }

            if ($clientes->isEmpty()) {
                $this->redirect('/portal');
                return;
            }

            if ($clientes->count() === 1) {
                session(['cliente_activo_id' => $clientes->first()->id]);
                $this->redirect('/portal');
                return;
            }

            $clienteActivoId = session('cliente_activo_id');
            if ($clienteActivoId && $clientes->contains('id', $clienteActivoId)) {
                $this->redirect('/portal');
                return;
            }
        }

    public function seleccionar(int $clienteId): void
    {
        $user = Auth::user();

        $existe = $user->clientes()->where('clientes.id', $clienteId)->exists();

        if (!$existe) {
            $this->addError('seleccion', 'No tienes acceso a esa empresa.');
            return;
        }

        session(['cliente_activo_id' => $clienteId]);

        $this->redirect('/portal');
    }

    public function getClientes()
    {
        return Auth::user()->clientes()->get();
    }
}