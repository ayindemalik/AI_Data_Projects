<?php

namespace App\Policies;

use App\Models\User;

class SubcategoryPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('manage-subcategories'); }
    public function create(User $user): bool { return $user->hasPermission('manage-subcategories'); }
    public function update(User $user): bool { return $user->hasPermission('manage-subcategories'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-subcategories'); }
}
