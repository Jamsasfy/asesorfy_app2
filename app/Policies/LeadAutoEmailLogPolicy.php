<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LeadAutoEmailLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadAutoEmailLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeadAutoEmailLog');
    }

    public function view(AuthUser $authUser, LeadAutoEmailLog $leadAutoEmailLog): bool
    {
        return $authUser->can('View:LeadAutoEmailLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeadAutoEmailLog');
    }

    public function update(AuthUser $authUser, LeadAutoEmailLog $leadAutoEmailLog): bool
    {
        return $authUser->can('Update:LeadAutoEmailLog');
    }

    public function delete(AuthUser $authUser, LeadAutoEmailLog $leadAutoEmailLog): bool
    {
        return $authUser->can('Delete:LeadAutoEmailLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LeadAutoEmailLog');
    }

}