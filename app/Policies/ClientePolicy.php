<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Cliente;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Cliente');
    }

    public function view(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('View:Cliente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Cliente');
    }

    public function update(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('Update:Cliente');
    }

    public function delete(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('Delete:Cliente');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Cliente');
    }

    public function asignarAsesor(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('AsignarAsesor:Cliente');
    }

    public function cambiarAsesor(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('CambiarAsesor:Cliente');
    }

    public function quitarAsesor(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('QuitarAsesor:Cliente');
    }

    public function asignacionMasivaAsesor(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('AsignacionMasivaAsesor:Cliente');
    }

    public function cambiarEstado(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('CambiarEstado:Cliente');
    }

}