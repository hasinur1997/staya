<?php

namespace Tests\Feature\Deposits;

use App\Actions\Ledger\PostAdjustment;
use App\Enums\DepositStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorksWithDeposits;
use Tests\TestCase;

class DepositReversalTest extends TestCase
{
    use RefreshDatabase, WorksWithDeposits;

    private function reverser(): User
    {
        return User::factory()->withPermissions(Permission::DepositViewAll, Permission::DepositReverse)->create();
    }

    public function test_reversing_posts_the_opposite_entry_and_marks_the_deposit()
    {
        $deposit = Deposit::factory()->approved()->create(['amount' => '2000.00', 'deposit_date' => '2026-10-10']);
        $reverser = $this->reverser();

        $this->actingAs($reverser)
            ->post(route('deposits.reverse', $deposit), ['reason' => 'Counted twice.'])
            ->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertSame(DepositStatus::Reversed, $deposit->status);
        $this->assertSame('Counted twice.', $deposit->reversal_reason);
        $this->assertTrue($deposit->reverser->is($reverser));

        $reversal = LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->sole();
        $this->assertSame(LedgerDirection::Debit, $reversal->direction);
        $this->assertSame('2026-10-10', $reversal->entry_date->toDateString());
        $this->assertSame($deposit->creditEntry->id, $reversal->reverses_entry_id);
        $this->assertFundMatchesLedger('0.00');

        $activity = Activity::query()->where('description', 'deposit_reversed')->sole();
        $this->assertSame('2000.00', $activity->attribute_changes['old']['balance']);
        $this->assertSame('0.00', $activity->attribute_changes['attributes']['balance']);
    }

    public function test_a_reversal_that_would_take_the_fund_below_zero_is_refused()
    {
        $deposit = Deposit::factory()->approved()->create(['amount' => '2000.00']);
        app(PostAdjustment::class)->handle(User::factory()->create(), [
            'direction' => LedgerDirection::Debit,
            'amount' => '1500.00',
            'entry_date' => '2026-10-16',
            'reason' => 'Spent',
        ]);

        $this->actingAs($this->reverser())
            ->post(route('deposits.reverse', $deposit), ['reason' => 'Mistake'])
            ->assertSessionHasErrors(['reason' => 'The fund does not have enough money. ৳ 1,500.00 is missing.']);

        $this->assertSame(DepositStatus::Approved, $deposit->fresh()->status);
        $this->assertFundMatchesLedger('500.00');
    }

    public function test_a_reversal_in_a_closed_month_is_refused()
    {
        $deposit = Deposit::factory()->approved()->create(['deposit_date' => '2026-09-25']);
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($this->reverser())
            ->post(route('deposits.reverse', $deposit), ['reason' => 'Mistake'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(DepositStatus::Approved, $deposit->fresh()->status);
    }

    public function test_a_deposit_is_reversed_only_once()
    {
        $deposit = Deposit::factory()->approved()->create();
        $reverser = $this->reverser();

        $this->actingAs($reverser)->post(route('deposits.reverse', $deposit), ['reason' => 'Mistake']);
        $this->actingAs($reverser)->post(route('deposits.reverse', $deposit), ['reason' => 'Again'])->assertForbidden();

        $this->assertSame(1, LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->count());
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_only_approved_deposits_can_be_reversed_and_it_needs_deposit_reverse()
    {
        $pending = Deposit::factory()->create();
        $approved = Deposit::factory()->approved()->create();

        $this->actingAs($this->reverser())
            ->post(route('deposits.reverse', $pending), ['reason' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->reviewer())
            ->post(route('deposits.reverse', $approved), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertSame(DepositStatus::Approved, $approved->fresh()->status);
    }

    public function test_reversal_needs_a_reason()
    {
        $deposit = Deposit::factory()->approved()->create();

        $this->actingAs($this->reverser())
            ->post(route('deposits.reverse', $deposit), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }
}
