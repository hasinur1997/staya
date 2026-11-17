<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\User;

/**
 * The audit log is read-only: it can be listed and opened with `audit.view`
 * and nothing else. The model itself refuses updates and deletes.
 */
class ActivityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AuditView->value);
    }

    public function view(User $actor, Activity $activity): bool
    {
        return $this->viewAny($actor);
    }
}
