<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MailSetting;
use App\Models\User;

class MailSettingPolicy
{
    /**
     * Determine whether the user can see or change how email is sent, and
     * send a test email. The page holds credentials, so this is its own
     * permission rather than part of `settings.update`.
     */
    public function manage(User $actor, MailSetting $settings): bool
    {
        return $actor->can(Permission::SettingsManageMail->value);
    }
}
