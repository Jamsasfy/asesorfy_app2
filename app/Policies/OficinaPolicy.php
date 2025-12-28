<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Oficina;
use Illuminate\Auth\Access\HandlesAuthorization;

class OficinaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Oficina');
    }

    public function view(AuthUser $authUser, Oficina $oficina): bool
    {
        return $authUser->can('View:Oficina');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Oficina');
    }

    public function update(AuthUser $authUser, Oficina $oficina): bool
    {
        return $authUser->can('Update:Oficina');
    }

    public function delete(AuthUser $authUser, Oficina $oficina): bool
    {
        return $authUser->can('Delete:Oficina');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Oficina');
    }

}