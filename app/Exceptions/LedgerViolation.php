<?php

namespace App\Exceptions;

use LogicException;

/**
 * A posting that breaks a ledger invariant. These are programming errors,
 * not user input problems, so they are never shown as validation messages.
 */
class LedgerViolation extends LogicException
{
    public static function nonPositiveAmount(string $amount): self
    {
        return new self("Ledger amounts must be greater than zero; got [{$amount}].");
    }

    public static function alreadyReversed(int $entryId): self
    {
        return new self("Ledger entry [{$entryId}] has already been reversed.");
    }

    public static function reversalOfReversal(int $entryId): self
    {
        return new self("Ledger entry [{$entryId}] is a reversal and cannot itself be reversed.");
    }

    public static function notInTransaction(): self
    {
        return new self('The fund can only be locked inside a database transaction.');
    }
}
