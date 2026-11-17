<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class MealPolicy
{
    /**
     * See every member's meals on the day screen.
     */
    public function viewAll(User $actor): bool
    {
        return $actor->can(Permission::MealViewAll->value) || $actor->can(Permission::MealRecord->value);
    }

    /**
     * See one's own meals and what they cost.
     */
    public function viewOwn(User $actor): bool
    {
        return $actor->can(Permission::MealViewOwn->value) && $actor->member !== null;
    }

    /**
     * Enter or change the meals of any member.
     */
    public function record(User $actor): bool
    {
        return $actor->can(Permission::MealRecord->value);
    }
}
