<?php

namespace App\Policies;

use App\Models\User;

class ProductTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-product-types');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-product-types');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-product-types');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-product-types');
    }
}
