<?php

namespace App\Actions\Deposits;

use App\Actions\Ledger\BusinessDateGuard;

/**
 * The shared date rules, reporting on the deposit date field by default.
 */
class DepositDateGuard extends BusinessDateGuard
{
    public function assertMonthOpen(string $date, string $field = 'deposit_date'): void
    {
        parent::assertMonthOpen($date, $field);
    }

    public function assertCountable(string $date, string $field = 'deposit_date'): void
    {
        parent::assertCountable($date, $field);
    }
}
