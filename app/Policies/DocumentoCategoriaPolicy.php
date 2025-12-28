<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DocumentoCategoria;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoCategoriaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentoCategoria');
    }

    public function view(AuthUser $authUser, DocumentoCategoria $documentoCategoria): bool
    {
        return $authUser->can('View:DocumentoCategoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentoCategoria');
    }

    public function update(AuthUser $authUser, DocumentoCategoria $documentoCategoria): bool
    {
        return $authUser->can('Update:DocumentoCategoria');
    }

    public function delete(AuthUser $authUser, DocumentoCategoria $documentoCategoria): bool
    {
        return $authUser->can('Delete:DocumentoCategoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DocumentoCategoria');
    }

}