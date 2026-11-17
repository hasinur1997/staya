<?php

namespace Tests\Feature\Deposits;

use App\Enums\DepositStatus;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithDeposits;
use Tests\TestCase;

class DepositRejectionAndCancellationTest extends TestCase
{
    use RefreshDatabase, WorksWithDeposits;

    public function test_rejection_needs_a_reason_which_the_member_sees()
    {
        $reviewer = $this->reviewer();
        $deposit = Deposit::factory()->create();

        $this->actingAs($reviewer)
            ->post(route('deposits.reject', $deposit), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($reviewer)
            ->post(route('deposits.reject', $deposit), ['reason' => 'No cash was handed over.'])
            ->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertSame(DepositStatus::Rejected, $deposit->status);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger('0.00');

        $this->actingAs($deposit->member->user)
            ->get(route('deposits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deposits.data.0.status', 'rejected')
                ->where('deposits.data.0.rejection_reason', 'No cash was handed over.'));

        $this->assertSame('No cash was handed over.', Activity::query()->where('description', 'deposit_rejected')->sole()->attribute_changes['attributes']['rejection_reason']);
    }

    public function test_rejection_requires_deposit_approve()
    {
        $deposit = Deposit::factory()->create();

        $this->actingAs($deposit->member->user)
            ->post(route('deposits.reject', $deposit), ['reason' => 'No'])
            ->assertForbidden();
    }

    public function test_rejection_is_refused_in_a_closed_month()
    {
        $deposit = Deposit::factory()->create(['deposit_date' => '2026-09-30']);
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($this->reviewer())
            ->post(route('deposits.reject', $deposit), ['reason' => 'No'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
    }

    public function test_a_member_cancels_their_own_pending_deposit_without_touching_the_ledger()
    {
        $deposit = Deposit::factory()->create();

        $this->actingAs($deposit->member->user)
            ->post(route('deposits.cancel', $deposit))
            ->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertSame(DepositStatus::Cancelled, $deposit->status);
        $this->assertNotNull($deposit->cancelled_at);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(1, Activity::query()->where('description', 'deposit_cancelled')->count());
    }

    public function test_only_ones_own_pending_deposits_can_be_cancelled()
    {
        $deposit = Deposit::factory()->create();
        $approved = Deposit::factory()->approved()->create();

        $this->actingAs($this->activeMember()->user)
            ->post(route('deposits.cancel', $deposit))
            ->assertForbidden();

        $this->actingAs($approved->member->user)
            ->post(route('deposits.cancel', $approved))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('deposits.cancel', $deposit))
            ->assertForbidden();

        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
    }

    public function test_cancelling_needs_cancel_own()
    {
        $deposit = Deposit::factory()->create();
        $deposit->member->user->syncRoles([]);
        $deposit->member->user->givePermissionTo(Permission::DepositViewOwn->value);

        $this->actingAs($deposit->member->user)
            ->post(route('deposits.cancel', $deposit))
            ->assertForbidden();
    }
}
