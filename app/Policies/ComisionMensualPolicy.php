<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ComisionMensual;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComisionMensualPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ComisionMensual');
    }

    public function view(AuthUser $authUser, ComisionMensual $comisionMensual): bool
    {
        return $authUser->can('View:ComisionMensual');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ComisionMensual');
    }

    public function update(AuthUser $authUser, ComisionMensual $comisionMensual): bool
    {
        return $authUser->can('Update:ComisionMensual');
    }

    public function delete(AuthUser $authUser, ComisionMensual $comisionMensual): bool
    {
        return $authUser->can('Delete:ComisionMensual');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ComisionMensual');
    }

}