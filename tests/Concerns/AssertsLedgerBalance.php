<?php

namespace Tests\Concerns;

use App\Models\Fund;
use App\Models\LedgerEntry;

/**
 * The fund invariant: the cached balance always equals the ledger sum.
 * Call after every financial action under test.
 */
trait AssertsLedgerBalance
{
    protected function assertFundMatchesLedger(?string $expected = null): void
    {
        $sum = '0.00';

        foreach (LedgerEntry::query()->lazyById() as $entry) {
            $sum = bcadd($sum, $entry->signedAmount(), 2);
        }

        $balance = Fund::current()->balance;

        $this->assertSame($sum, $balance, 'The cached fund balance does not equal the ledger sum.');

        if ($expected !== null) {
            $this->assertSame($expected, $balance);
        }
    }
}
