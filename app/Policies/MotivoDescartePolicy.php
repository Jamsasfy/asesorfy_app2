<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MotivoDescarte;
use Illuminate\Auth\Access\HandlesAuthorization;

class MotivoDescartePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MotivoDescarte');
    }

    public function view(AuthUser $authUser, MotivoDescarte $motivoDescarte): bool
    {
        return $authUser->can('View:MotivoDescarte');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MotivoDescarte');
    }

    public function update(AuthUser $authUser, MotivoDescarte $motivoDescarte): bool
    {
        return $authUser->can('Update:MotivoDescarte');
    }

    public function delete(AuthUser $authUser, MotivoDescarte $motivoDescarte): bool
    {
        return $authUser->can('Delete:MotivoDescarte');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MotivoDescarte');
    }

}