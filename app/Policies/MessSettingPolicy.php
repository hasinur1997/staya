<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessSetting;
use App\Models\User;

class MessSettingPolicy
{
    /**
     * Determine whether the user can view the mess settings.
     */
    public function view(User $actor, MessSetting $settings): bool
    {
        return $actor->can(Permission::SettingsView->value);
    }

    /**
     * Determine whether the user can change the mess settings.
     */
    public function update(User $actor, MessSetting $settings): bool
    {
        return $actor->can(Permission::SettingsUpdate->value);
    }

    /**
     * Determine whether the user can erase all financial and meal data.
     */
    public function resetData(User $actor, MessSetting $settings): bool
    {
        return $actor->can(Permission::SettingsResetData->value);
    }
}
