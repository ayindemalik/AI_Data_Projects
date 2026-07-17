<?php

namespace App\Policies;

use App\Models\User;

class SeriesPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('manage-series'); }
    public function create(User $user): bool { return $user->hasPermission('manage-series'); }
    public function update(User $user): bool { return $user->hasPermission('manage-series'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-series'); }
}
