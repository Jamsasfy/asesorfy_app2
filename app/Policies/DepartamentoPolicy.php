<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Departamento;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartamentoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Departamento');
    }

    public function view(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('View:Departamento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Departamento');
    }

    public function update(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('Update:Departamento');
    }

    public function delete(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('Delete:Departamento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Departamento');
    }

}