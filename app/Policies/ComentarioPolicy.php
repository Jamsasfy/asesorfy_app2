<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Comentario;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComentarioPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Comentario');
    }

    public function view(AuthUser $authUser, Comentario $comentario): bool
    {
        return $authUser->can('View:Comentario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Comentario');
    }

    public function update(AuthUser $authUser, Comentario $comentario): bool
    {
        return $authUser->can('Update:Comentario');
    }

    public function delete(AuthUser $authUser, Comentario $comentario): bool
    {
        return $authUser->can('Delete:Comentario');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Comentario');
    }

}