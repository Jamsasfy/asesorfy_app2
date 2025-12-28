<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Procedencia;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcedenciaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Procedencia');
    }

    public function view(AuthUser $authUser, Procedencia $procedencia): bool
    {
        return $authUser->can('View:Procedencia');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Procedencia');
    }

    public function update(AuthUser $authUser, Procedencia $procedencia): bool
    {
        return $authUser->can('Update:Procedencia');
    }

    public function delete(AuthUser $authUser, Procedencia $procedencia): bool
    {
        return $authUser->can('Delete:Procedencia');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Procedencia');
    }

}