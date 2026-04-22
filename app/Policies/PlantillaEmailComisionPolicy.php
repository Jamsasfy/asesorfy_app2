<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantillaEmailComision;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantillaEmailComisionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantillaEmailComision');
    }

    public function view(AuthUser $authUser, PlantillaEmailComision $plantillaEmailComision): bool
    {
        return $authUser->can('View:PlantillaEmailComision');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantillaEmailComision');
    }

    public function update(AuthUser $authUser, PlantillaEmailComision $plantillaEmailComision): bool
    {
        return $authUser->can('Update:PlantillaEmailComision');
    }

    public function delete(AuthUser $authUser, PlantillaEmailComision $plantillaEmailComision): bool
    {
        return $authUser->can('Delete:PlantillaEmailComision');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PlantillaEmailComision');
    }

}