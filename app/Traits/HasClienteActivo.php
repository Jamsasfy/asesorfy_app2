<?php

namespace App\Traits;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;

trait HasClienteActivo
{
    /**
     * Devuelve el cliente activo en sesión.
     * Lanza una excepción si no hay ninguno (no debería ocurrir
     * si el middleware está bien configurado).
     */
    public function clienteActivo(): Cliente
    {
        $id = session('cliente_activo_id');

        if (!$id) {
            redirect('/portal/seleccionar-empresa')->send();
            exit;
        }

        $cliente = Auth::user()
            ->clientes()
            ->where('clientes.id', $id)
            ->first();

        if (!$cliente) {
            session()->forget('cliente_activo_id');
            redirect('/portal/seleccionar-empresa')->send();
            exit;
        }

        return $cliente;
    }

    /**
     * Devuelve el ID del cliente activo directamente.
     * Útil para queries sin necesidad de cargar el modelo completo.
     */
    public function clienteActivoId(): int
    {
        return $this->clienteActivo()->id;
    }

    /**
     * Devuelve el cliente activo o null si no hay ninguno.
     * Útil para comprobaciones condicionales en vistas.
     */
    public function clienteActivoONull(): ?Cliente
    {
        $id = session('cliente_activo_id');
        if (!$id) return null;

        return Auth::user()
            ->clientes()
            ->where('clientes.id', $id)
            ->first();
    }
}