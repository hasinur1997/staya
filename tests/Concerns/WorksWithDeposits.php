<?php

namespace Tests\Concerns;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\Member;
use App\Models\User;

/**
 * Shared setup for deposit tests: a fixed "now" (18 Oct 2026, noon in
 * Dhaka), members and reviewers.
 */
trait WorksWithDeposits
{
    use AssertsLedgerBalance, SeedsAccessControl;

    protected function setUpWorksWithDeposits(): void
    {
        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    protected function activeMember(): Member
    {
        return Member::factory()->active()->create();
    }

    protected function reviewer(Permission ...$extra): User
    {
        return User::factory()->withPermissions(Permission::DepositViewAll, Permission::DepositApprove, ...$extra)->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function depositForm(array $overrides = []): array
    {
        return ['amount' => '2500.00', 'deposit_date' => '2026-10-15', ...$overrides];
    }
}
