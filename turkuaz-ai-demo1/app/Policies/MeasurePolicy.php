<?php

namespace App\Policies;

use App\Models\User;

class MeasurePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('manage-measures'); }
    public function create(User $user): bool { return $user->hasPermission('manage-measures'); }
    public function update(User $user): bool { return $user->hasPermission('manage-measures'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-measures'); }
}
