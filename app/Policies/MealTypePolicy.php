<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Meal types are part of the mess settings.
 */
class MealTypePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::SettingsView->value);
    }

    public function manage(User $actor): bool
    {
        return $actor->can(Permission::SettingsUpdate->value);
    }
}
