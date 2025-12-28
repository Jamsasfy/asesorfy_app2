<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmailTemplate');
    }

    public function view(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return $authUser->can('View:EmailTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmailTemplate');
    }

    public function update(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return $authUser->can('Update:EmailTemplate');
    }

    public function delete(AuthUser $authUser, EmailTemplate $emailTemplate): bool
    {
        return $authUser->can('Delete:EmailTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmailTemplate');
    }

}