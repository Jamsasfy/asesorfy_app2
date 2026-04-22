<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConfiguracionComisiones;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConfiguracionComisionesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConfiguracionComisiones');
    }

    public function view(AuthUser $authUser, ConfiguracionComisiones $configuracionComisiones): bool
    {
        return $authUser->can('View:ConfiguracionComisiones');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConfiguracionComisiones');
    }

    public function update(AuthUser $authUser, ConfiguracionComisiones $configuracionComisiones): bool
    {
        return $authUser->can('Update:ConfiguracionComisiones');
    }

    public function delete(AuthUser $authUser, ConfiguracionComisiones $configuracionComisiones): bool
    {
        return $authUser->can('Delete:ConfiguracionComisiones');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ConfiguracionComisiones');
    }

}