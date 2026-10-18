<?php

namespace Tests\Feature\Ledger;

use App\Actions\Ledger\PostLedgerEntries;
use App\Actions\Ledger\ReverseLedgerEntry;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Exceptions\MonthClosed;
use App\Models\LedgerEntry;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsLedgerBalance;
use Tests\TestCase;

class ClosedMonthGuardTest extends TestCase
{
    use AssertsLedgerBalance, RefreshDatabase;

    private function postCredit(string $date, string $amount = '100.00'): LedgerEntry
    {
        return app(PostLedgerEntries::class)->handle(User::factory()->create(), [[
            'type' => LedgerEntryType::Adjustment,
            'direction' => LedgerDirection::Credit,
            'amount' => $amount,
            'entry_date' => $date,
            'reason' => 'Test',
        ]])[0];
    }

    public function test_only_the_closed_month_is_blocked_at_its_boundaries()
    {
        MonthClosure::factory()->forMonth('2026-10-01')->create();

        $this->assertFalse(MonthClosure::isClosed('2026-09-30'));
        $this->assertTrue(MonthClosure::isClosed('2026-10-01'));
        $this->assertTrue(MonthClosure::isClosed('2026-10-31'));
        $this->assertFalse(MonthClosure::isClosed('2026-11-01'));
    }

    public function test_a_reopened_month_is_open()
    {
        MonthClosure::factory()->forMonth('2026-10-01')->reopened()->create();

        $this->assertFalse(MonthClosure::isClosed('2026-10-15'));
    }

    public function test_posting_into_a_closed_month_is_refused()
    {
        MonthClosure::factory()->forMonth('2026-10-01')->create();

        try {
            $this->postCredit('2026-10-31');
            $this->fail('Posting into a closed month should be refused.');
        } catch (MonthClosed $exception) {
            $this->assertSame(
                'October 2026 is closed. Reopen it before changing anything dated in it.',
                $exception->errors()['entry_date'][0],
            );
        }

        $this->postCredit('2026-11-01');
        $this->assertFundMatchesLedger('100.00');
    }

    public function test_an_entry_in_a_closed_month_cannot_be_reversed()
    {
        $entry = $this->postCredit('2026-09-20');
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->expectException(MonthClosed::class);

        try {
            app(ReverseLedgerEntry::class)->handle(User::factory()->create(), $entry, 'Mistake');
        } finally {
            $this->assertSame(1, LedgerEntry::query()->count());
            $this->assertFundMatchesLedger('100.00');
        }
    }
}
