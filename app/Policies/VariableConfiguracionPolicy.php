<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VariableConfiguracion;
use Illuminate\Auth\Access\HandlesAuthorization;

class VariableConfiguracionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VariableConfiguracion');
    }

    public function view(AuthUser $authUser, VariableConfiguracion $variableConfiguracion): bool
    {
        return $authUser->can('View:VariableConfiguracion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VariableConfiguracion');
    }

    public function update(AuthUser $authUser, VariableConfiguracion $variableConfiguracion): bool
    {
        return $authUser->can('Update:VariableConfiguracion');
    }

    public function delete(AuthUser $authUser, VariableConfiguracion $variableConfiguracion): bool
    {
        return $authUser->can('Delete:VariableConfiguracion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VariableConfiguracion');
    }

}