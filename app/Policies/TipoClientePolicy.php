<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TipoCliente;
use Illuminate\Auth\Access\HandlesAuthorization;

class TipoClientePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TipoCliente');
    }

    public function view(AuthUser $authUser, TipoCliente $tipoCliente): bool
    {
        return $authUser->can('View:TipoCliente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TipoCliente');
    }

    public function update(AuthUser $authUser, TipoCliente $tipoCliente): bool
    {
        return $authUser->can('Update:TipoCliente');
    }

    public function delete(AuthUser $authUser, TipoCliente $tipoCliente): bool
    {
        return $authUser->can('Delete:TipoCliente');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TipoCliente');
    }

}