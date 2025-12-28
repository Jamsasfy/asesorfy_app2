<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DocumentoSubtipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoSubtipoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentoSubtipo');
    }

    public function view(AuthUser $authUser, DocumentoSubtipo $documentoSubtipo): bool
    {
        return $authUser->can('View:DocumentoSubtipo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentoSubtipo');
    }

    public function update(AuthUser $authUser, DocumentoSubtipo $documentoSubtipo): bool
    {
        return $authUser->can('Update:DocumentoSubtipo');
    }

    public function delete(AuthUser $authUser, DocumentoSubtipo $documentoSubtipo): bool
    {
        return $authUser->can('Delete:DocumentoSubtipo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DocumentoSubtipo');
    }

}