<?php

namespace Tests\Feature\Ledger;

use App\Actions\Ledger\PostLedgerEntries;
use App\Actions\Ledger\ReverseLedgerEntry;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Exceptions\AppendOnlyViolation;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\LedgerViolation;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsLedgerBalance;
use Tests\TestCase;

class PostLedgerEntriesTest extends TestCase
{
    use AssertsLedgerBalance, RefreshDatabase;

    private function postEntry(LedgerDirection $direction, string $amount, string $date = '2026-10-10'): LedgerEntry
    {
        return app(PostLedgerEntries::class)->handle(User::factory()->create(), [[
            'type' => LedgerEntryType::Adjustment,
            'direction' => $direction,
            'amount' => $amount,
            'entry_date' => $date,
            'reason' => 'Test',
        ]])[0];
    }

    public function test_the_fund_row_exists_after_migrating()
    {
        $this->assertSame(1, Fund::query()->count());
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_credits_and_debits_move_the_cached_balance_with_exact_decimals()
    {
        $this->postEntry(LedgerDirection::Credit, '0.10');
        $this->postEntry(LedgerDirection::Credit, '0.20');
        $this->assertFundMatchesLedger('0.30');

        $this->postEntry(LedgerDirection::Credit, '9999999999.99');
        $this->postEntry(LedgerDirection::Debit, '9999999999.98');
        $this->assertFundMatchesLedger('0.31');
    }

    public function test_entries_are_stored_with_plain_business_dates()
    {
        $entry = $this->postEntry(LedgerDirection::Credit, '10', '2026-10-31');

        $this->assertSame('10.00', $entry->amount);
        $this->assertSame('2026-10-31', DB::table('ledger_entries')->value('entry_date'));
        $this->assertSame(1, LedgerEntry::query()->where('entry_date', '2026-10-31')->count());
    }

    public function test_a_debit_below_zero_is_rejected_with_the_missing_amount()
    {
        $this->postEntry(LedgerDirection::Credit, '100.00');

        try {
            $this->postEntry(LedgerDirection::Debit, '150.50');
            $this->fail('The debit should have been rejected.');
        } catch (InsufficientFunds $exception) {
            $this->assertSame('50.50', $exception->missing);
            $this->assertSame(
                'The fund does not have enough money. ৳ 50.50 is missing.',
                $exception->errors()['amount'][0],
            );
        }

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger('100.00');
    }

    public function test_a_debit_down_to_exactly_zero_is_allowed()
    {
        $this->postEntry(LedgerDirection::Credit, '100.00');
        $this->postEntry(LedgerDirection::Debit, '100.00');

        $this->assertFundMatchesLedger('0.00');
    }

    public function test_a_batch_is_checked_against_its_final_balance_and_written_all_or_nothing()
    {
        $actor = User::factory()->create();
        $entry = fn (LedgerDirection $direction, string $amount) => [
            'type' => LedgerEntryType::Adjustment,
            'direction' => $direction,
            'amount' => $amount,
            'entry_date' => '2026-10-10',
            'reason' => 'Test',
        ];

        // Debit first, credit second: allowed, because the batch ends at 20.
        app(PostLedgerEntries::class)->handle($actor, [
            $entry(LedgerDirection::Debit, '30.00'),
            $entry(LedgerDirection::Credit, '50.00'),
        ]);
        $this->assertFundMatchesLedger('20.00');

        $this->expectException(InsufficientFunds::class);

        try {
            app(PostLedgerEntries::class)->handle($actor, [
                $entry(LedgerDirection::Credit, '5.00'),
                $entry(LedgerDirection::Debit, '30.00'),
            ]);
        } finally {
            $this->assertSame(2, LedgerEntry::query()->count());
            $this->assertFundMatchesLedger('20.00');
        }
    }

    public function test_zero_and_negative_amounts_are_refused()
    {
        foreach (['0', '0.00', '-5.00'] as $amount) {
            try {
                $this->postEntry(LedgerDirection::Credit, $amount);
                $this->fail("[{$amount}] should have been refused.");
            } catch (LedgerViolation) {
                // Expected.
            }
        }

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_the_balance_is_read_under_the_lock_not_from_a_stale_copy()
    {
        $this->postEntry(LedgerDirection::Credit, '100.00');
        $stale = Fund::current();

        // Another request spends 80 after this one loaded the fund.
        $this->postEntry(LedgerDirection::Debit, '80.00');
        $this->assertSame('100.00', $stale->balance);

        try {
            $this->postEntry(LedgerDirection::Debit, '50.00');
            $this->fail('The second debit should have been checked against ৳ 20.00.');
        } catch (InsufficientFunds $exception) {
            $this->assertSame('30.00', $exception->missing);
        }

        $this->assertFundMatchesLedger('20.00');
    }

    public function test_ledger_entries_cannot_be_updated()
    {
        $entry = $this->postEntry(LedgerDirection::Credit, '100.00');

        $this->expectException(AppendOnlyViolation::class);

        $entry->update(['amount' => '1.00']);
    }

    public function test_ledger_entries_cannot_be_deleted()
    {
        $entry = $this->postEntry(LedgerDirection::Credit, '100.00');

        try {
            $entry->delete();
            $this->fail('Deleting a ledger entry should throw.');
        } catch (AppendOnlyViolation) {
            // Expected.
        }

        $this->assertModelExists($entry);
        $this->assertFundMatchesLedger('100.00');
    }

    public function test_a_reversal_posts_the_opposite_entry_on_the_original_date()
    {
        $entry = $this->postEntry(LedgerDirection::Credit, '100.00', '2026-09-15');

        $reversal = app(ReverseLedgerEntry::class)->handle(User::factory()->create(), $entry, 'Posted twice');

        $this->assertSame(LedgerEntryType::Reversal, $reversal->type);
        $this->assertSame(LedgerDirection::Debit, $reversal->direction);
        $this->assertSame('100.00', $reversal->amount);
        $this->assertSame('2026-09-15', $reversal->entry_date->toDateString());
        $this->assertSame($entry->id, $reversal->reverses_entry_id);
        $this->assertSame('Posted twice', $reversal->reason);
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_an_entry_cannot_be_reversed_twice()
    {
        $this->postEntry(LedgerDirection::Credit, '500.00');
        $entry = $this->postEntry(LedgerDirection::Credit, '100.00');
        $actor = User::factory()->create();

        app(ReverseLedgerEntry::class)->handle($actor, $entry, 'First');

        try {
            app(ReverseLedgerEntry::class)->handle($actor, $entry, 'Second');
            $this->fail('A second reversal should be refused.');
        } catch (LedgerViolation) {
            // Expected.
        }

        $this->assertFundMatchesLedger('500.00');

        // The unique index backs the rule up even if the action is bypassed.
        $this->expectException(UniqueConstraintViolationException::class);

        LedgerEntry::factory()->debit()->create([
            'type' => LedgerEntryType::Reversal,
            'amount' => '100.00',
            'reverses_entry_id' => $entry->id,
        ]);
    }

    public function test_a_reversal_cannot_itself_be_reversed()
    {
        $entry = $this->postEntry(LedgerDirection::Credit, '100.00');
        $actor = User::factory()->create();
        $reversal = app(ReverseLedgerEntry::class)->handle($actor, $entry, 'Mistake');

        $this->expectException(LedgerViolation::class);

        app(ReverseLedgerEntry::class)->handle($actor, $reversal, 'Undo the reversal');
    }

    public function test_reversing_a_credit_is_subject_to_the_floor()
    {
        $entry = $this->postEntry(LedgerDirection::Credit, '100.00');
        $this->postEntry(LedgerDirection::Debit, '70.00');

        try {
            app(ReverseLedgerEntry::class)->handle(User::factory()->create(), $entry, 'Mistake');
            $this->fail('The reversal should have been rejected.');
        } catch (InsufficientFunds $exception) {
            $this->assertSame('70.00', $exception->missing);
        }

        $this->assertFundMatchesLedger('30.00');
    }
}
