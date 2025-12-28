<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ClienteSuscripcion;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClienteSuscripcionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClienteSuscripcion');
    }

    public function view(AuthUser $authUser, ClienteSuscripcion $clienteSuscripcion): bool
    {
        return $authUser->can('View:ClienteSuscripcion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClienteSuscripcion');
    }

    public function update(AuthUser $authUser, ClienteSuscripcion $clienteSuscripcion): bool
    {
        return $authUser->can('Update:ClienteSuscripcion');
    }

    public function delete(AuthUser $authUser, ClienteSuscripcion $clienteSuscripcion): bool
    {
        return $authUser->can('Delete:ClienteSuscripcion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClienteSuscripcion');
    }

}