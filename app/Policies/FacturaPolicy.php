<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Factura;
use Illuminate\Auth\Access\HandlesAuthorization;

class FacturaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Factura');
    }

    public function view(AuthUser $authUser, Factura $factura): bool
    {
        return $authUser->can('View:Factura');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Factura');
    }

    public function update(AuthUser $authUser, Factura $factura): bool
    {
        return $authUser->can('Update:Factura');
    }

    public function delete(AuthUser $authUser, Factura $factura): bool
    {
        return $authUser->can('Delete:Factura');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Factura');
    }

}