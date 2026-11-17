<?php

namespace App\Policies;

use App\Actions\Reports\MonthlyReport;
use App\Enums\Permission;
use App\Models\User;

/**
 * Who may see monthly figures. Checked against the `MonthlyReport` class
 * (`Gate::allows('viewOwn', MonthlyReport::class)`).
 */
class ReportPolicy
{
    /**
     * The viewer's own deposits, share and due or credit.
     */
    public function viewOwn(User $actor, string $report = MonthlyReport::class): bool
    {
        return $actor->can(Permission::ReportViewOwn->value) || $this->viewAll($actor);
    }

    /**
     * Closing and reopening months.
     */
    public function closeMonth(User $actor, string $report = MonthlyReport::class): bool
    {
        return $actor->can(Permission::ReportCloseMonth->value);
    }

    /**
     * The per-member table with everyone's figures.
     */
    public function viewAll(User $actor, string $report = MonthlyReport::class): bool
    {
        return $actor->can(Permission::ReportViewAll->value);
    }
}
