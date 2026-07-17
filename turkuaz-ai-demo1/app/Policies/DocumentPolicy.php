<?php

namespace App\Policies;

use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('manage-documents'); }
    public function create(User $user): bool { return $user->hasPermission('manage-documents'); }
    public function update(User $user): bool { return $user->hasPermission('manage-documents'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-documents'); }
}
