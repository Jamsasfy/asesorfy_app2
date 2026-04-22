<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ComercialHistorialObjetivo;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComercialHistorialObjetivoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ComercialHistorialObjetivo');
    }

    public function view(AuthUser $authUser, ComercialHistorialObjetivo $comercialHistorialObjetivo): bool
    {
        return $authUser->can('View:ComercialHistorialObjetivo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ComercialHistorialObjetivo');
    }

    public function update(AuthUser $authUser, ComercialHistorialObjetivo $comercialHistorialObjetivo): bool
    {
        return $authUser->can('Update:ComercialHistorialObjetivo');
    }

    public function delete(AuthUser $authUser, ComercialHistorialObjetivo $comercialHistorialObjetivo): bool
    {
        return $authUser->can('Delete:ComercialHistorialObjetivo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ComercialHistorialObjetivo');
    }

}