<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlantillaContrato;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlantillaContratoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlantillaContrato');
    }

    public function view(AuthUser $authUser, PlantillaContrato $plantillaContrato): bool
    {
        return $authUser->can('View:PlantillaContrato');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlantillaContrato');
    }

    public function update(AuthUser $authUser, PlantillaContrato $plantillaContrato): bool
    {
        return $authUser->can('Update:PlantillaContrato');
    }

    public function delete(AuthUser $authUser, PlantillaContrato $plantillaContrato): bool
    {
        return $authUser->can('Delete:PlantillaContrato');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PlantillaContrato');
    }

}