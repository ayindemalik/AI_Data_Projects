<?php

namespace App\Policies;

use App\Models\User;

class ColorPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('manage-colors'); }
    public function create(User $user): bool { return $user->hasPermission('manage-colors'); }
    public function update(User $user): bool { return $user->hasPermission('manage-colors'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-colors'); }
}
