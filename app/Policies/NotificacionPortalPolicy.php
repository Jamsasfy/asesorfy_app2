<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NotificacionPortal;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificacionPortalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotificacionPortal');
    }

    public function view(AuthUser $authUser, NotificacionPortal $notificacionPortal): bool
    {
        return $authUser->can('View:NotificacionPortal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotificacionPortal');
    }

    public function update(AuthUser $authUser, NotificacionPortal $notificacionPortal): bool
    {
        return $authUser->can('Update:NotificacionPortal');
    }

    public function delete(AuthUser $authUser, NotificacionPortal $notificacionPortal): bool
    {
        return $authUser->can('Delete:NotificacionPortal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NotificacionPortal');
    }

    public function enviar(AuthUser $authUser, NotificacionPortal $notificacionPortal): bool
    {
        return $authUser->can('Enviar:NotificacionPortal');
    }

}