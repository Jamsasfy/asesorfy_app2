<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ComisionRegla;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComisionReglaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ComisionRegla');
    }

    public function view(AuthUser $authUser, ComisionRegla $comisionRegla): bool
    {
        return $authUser->can('View:ComisionRegla');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ComisionRegla');
    }

    public function update(AuthUser $authUser, ComisionRegla $comisionRegla): bool
    {
        return $authUser->can('Update:ComisionRegla');
    }

    public function delete(AuthUser $authUser, ComisionRegla $comisionRegla): bool
    {
        return $authUser->can('Delete:ComisionRegla');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ComisionRegla');
    }

}