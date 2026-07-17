<?php

namespace App\Policies;

use App\Models\User;

class DocumentCategoryPolicy
{
    // Deliberately reuses 'manage-documents' rather than adding a separate
    // permission — document categories are part of the same admin duty.
    public function viewAny(User $user): bool { return $user->hasPermission('manage-documents'); }
    public function create(User $user): bool { return $user->hasPermission('manage-documents'); }
    public function update(User $user): bool { return $user->hasPermission('manage-documents'); }
    public function delete(User $user): bool { return $user->hasPermission('manage-documents'); }
}
