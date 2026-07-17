<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('manage-products'); }
    public function create(User $user): bool { return $user->hasPermission('manage-products'); }
    public function update(User $user): bool { return $user->hasPermission('manage-products'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-products'); }
}
