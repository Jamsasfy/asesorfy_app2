<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Servicio;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServicioPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Servicio');
    }

    public function view(AuthUser $authUser, Servicio $servicio): bool
    {
        return $authUser->can('View:Servicio');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Servicio');
    }

    public function update(AuthUser $authUser, Servicio $servicio): bool
    {
        return $authUser->can('Update:Servicio');
    }

    public function delete(AuthUser $authUser, Servicio $servicio): bool
    {
        return $authUser->can('Delete:Servicio');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Servicio');
    }

}