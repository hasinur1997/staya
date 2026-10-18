<?php

namespace Tests\Feature\Ledger;

use App\Actions\Ledger\PostLedgerEntries;
use App\Actions\Ledger\ReverseLedgerEntry;
use App\Actions\Ledger\SummarizeLedgerPeriod;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsLedgerBalance;
use Tests\TestCase;

class LedgerPeriodSummaryTest extends TestCase
{
    use AssertsLedgerBalance, RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
    }

    private function postEntry(LedgerEntryType $type, LedgerDirection $direction, string $amount, string $date): LedgerEntry
    {
        return app(PostLedgerEntries::class)->handle($this->actor, [[
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'entry_date' => $date,
            'reason' => 'Test',
        ]])[0];
    }

    public function test_a_period_summary_matches_a_hand_worked_fixture()
    {
        // September: opening 1,000, a deposit of 3,000, an expense of 1,200.50.
        $this->postEntry(LedgerEntryType::OpeningBalance, LedgerDirection::Credit, '1000.00', '2026-09-01');
        $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '3000.00', '2026-09-10');
        $this->postEntry(LedgerEntryType::GroceryExpense, LedgerDirection::Debit, '1200.50', '2026-09-28');

        // October: deposits 2,500 + 500 (the 500 later reversed), expenses
        // 800.25, an adjustment of −0.25, a reimbursement of 300.
        $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '2500.00', '2026-10-01');
        $reversed = $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '500.00', '2026-10-15');
        app(ReverseLedgerEntry::class)->handle($this->actor, $reversed, 'Counted twice');
        $this->postEntry(LedgerEntryType::GroceryExpense, LedgerDirection::Debit, '800.25', '2026-10-20');
        $this->postEntry(LedgerEntryType::Adjustment, LedgerDirection::Debit, '0.25', '2026-10-31');
        $this->postEntry(LedgerEntryType::Reimbursement, LedgerDirection::Debit, '300.00', '2026-10-31');

        // November: a deposit that must not appear in October.
        $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '999.00', '2026-11-01');

        $summarize = app(SummarizeLedgerPeriod::class);

        $this->assertSame([
            'opening' => '1000.00',
            'deposits' => '3000.00',
            'expenses' => '1200.50',
            'adjustments' => '0.00',
            'reimbursements' => '0.00',
            'closing' => '2799.50',
        ], $summarize->handle('2026-09-01', '2026-09-30'));

        $this->assertSame([
            'opening' => '2799.50',
            'deposits' => '2500.00',
            'expenses' => '800.25',
            'adjustments' => '-0.25',
            'reimbursements' => '300.00',
            'closing' => '4199.00',
        ], $summarize->handle('2026-10-01', '2026-10-31'));

        $november = $summarize->handle('2026-11-01', '2026-11-30');
        $this->assertSame('4199.00', $november['opening']);
        $this->assertSame('5198.00', $november['closing']);

        // Closing of the whole history equals the cached balance.
        $this->assertFundMatchesLedger($november['closing']);
    }
}
