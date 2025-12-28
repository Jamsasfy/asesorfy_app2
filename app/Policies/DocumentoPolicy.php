<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Documento;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Documento');
    }

    public function view(AuthUser $authUser, Documento $documento): bool
    {
        return $authUser->can('View:Documento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Documento');
    }

    public function update(AuthUser $authUser, Documento $documento): bool
    {
        return $authUser->can('Update:Documento');
    }

    public function delete(AuthUser $authUser, Documento $documento): bool
    {
        return $authUser->can('Delete:Documento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Documento');
    }

    public function verificar(AuthUser $authUser, Documento $documento): bool
    {
        return $authUser->can('Verificar:Documento');
    }

}