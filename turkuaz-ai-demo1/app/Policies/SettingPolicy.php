<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    /**
     * A single ability covers the whole Settings screen — there's nothing
     * to split into separate view/create/update abilities here.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('manage-settings');
    }
}
