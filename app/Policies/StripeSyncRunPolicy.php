<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StripeSyncRun;
use Illuminate\Auth\Access\HandlesAuthorization;

class StripeSyncRunPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StripeSyncRun');
    }

    public function view(AuthUser $authUser, StripeSyncRun $stripeSyncRun): bool
    {
        return $authUser->can('View:StripeSyncRun');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StripeSyncRun');
    }

    public function update(AuthUser $authUser, StripeSyncRun $stripeSyncRun): bool
    {
        return $authUser->can('Update:StripeSyncRun');
    }

    public function delete(AuthUser $authUser, StripeSyncRun $stripeSyncRun): bool
    {
        return $authUser->can('Delete:StripeSyncRun');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StripeSyncRun');
    }

}