<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Trabajador;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrabajadorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Trabajador');
    }

    public function view(AuthUser $authUser, Trabajador $trabajador): bool
    {
        return $authUser->can('View:Trabajador');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Trabajador');
    }

    public function update(AuthUser $authUser, Trabajador $trabajador): bool
    {
        return $authUser->can('Update:Trabajador');
    }

    public function delete(AuthUser $authUser, Trabajador $trabajador): bool
    {
        return $authUser->can('Delete:Trabajador');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Trabajador');
    }

}