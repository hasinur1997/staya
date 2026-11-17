<?php

namespace Tests\Feature\Deposits;

use App\Enums\DepositStatus;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorksWithDeposits;
use Tests\TestCase;

class DepositSubmissionTest extends TestCase
{
    use RefreshDatabase, WorksWithDeposits;

    public function test_a_member_submits_a_pending_deposit_that_does_not_touch_the_balance()
    {
        $member = $this->activeMember();

        $this->actingAs($member->user)
            ->post(route('deposits.store'), $this->depositForm())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $deposit = Deposit::query()->sole();
        $this->assertSame(DepositStatus::Pending, $deposit->status);
        $this->assertSame('2500.00', $deposit->amount);
        $this->assertTrue($deposit->member->is($member));
        $this->assertSame($member->user_id, $deposit->submitted_by);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger('0.00');

        $activity = Activity::query()->where('description', 'deposit_submitted')->sole();
        $this->assertTrue($activity->causer->is($member->user));
        $this->assertSame('2500.00', $activity->attribute_changes['attributes']['amount']);
    }

    public function test_the_amount_must_be_a_positive_decimal_with_two_places()
    {
        $member = $this->activeMember();

        foreach (['0', '0.00', '-5', '1.234', 'abc', '1e3', ''] as $amount) {
            $this->actingAs($member->user)
                ->post(route('deposits.store'), $this->depositForm(['amount' => $amount]))
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, Deposit::query()->count());
    }

    public function test_future_dated_deposits_are_rejected()
    {
        $this->actingAs($this->activeMember()->user)
            ->post(route('deposits.store'), $this->depositForm(['deposit_date' => '2026-10-19']))
            ->assertSessionHasErrors(['deposit_date' => 'The date cannot be in the future.']);
    }

    public function test_deposits_before_the_fund_start_date_are_rejected()
    {
        MessSetting::current()->update(['fund_start_date' => '2026-10-16']);

        $this->actingAs($this->activeMember()->user)
            ->post(route('deposits.store'), $this->depositForm(['deposit_date' => '2026-10-15']))
            ->assertSessionHasErrors('deposit_date');
    }

    public function test_deposits_dated_in_a_closed_month_are_rejected()
    {
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($this->activeMember()->user)
            ->post(route('deposits.store'), $this->depositForm(['deposit_date' => '2026-09-30']))
            ->assertSessionHasErrors(['deposit_date' => 'September 2026 is closed. Reopen it before changing anything dated in it.']);
    }

    public function test_an_inactive_member_gets_403_on_submit()
    {
        $member = Member::factory()->inactive()->create();

        $this->actingAs($member->user)
            ->post(route('deposits.store'), $this->depositForm())
            ->assertForbidden();

        $this->actingAs($member->user)->get(route('deposits.create'))->assertForbidden();
        $this->assertSame(0, Deposit::query()->count());
    }

    public function test_users_without_a_member_profile_cannot_submit_their_own()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::DepositCreateOwn)->create())
            ->post(route('deposits.store'), $this->depositForm())
            ->assertForbidden();
    }

    public function test_the_deposit_month_must_overlap_one_of_the_members_stays()
    {
        // The factory's stay starts a month before "today": 18 Sep 2026.
        $member = $this->activeMember();

        $this->actingAs($member->user)
            ->post(route('deposits.store'), $this->depositForm(['deposit_date' => '2026-08-31']))
            ->assertSessionHasErrors('deposit_date');
    }

    public function test_staff_record_a_deposit_for_another_member()
    {
        $member = $this->activeMember();
        $staff = User::factory()->withPermissions(Permission::DepositCreateForOthers)->create();

        $this->actingAs($staff)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $member->id]))
            ->assertSessionHasNoErrors();

        $deposit = Deposit::query()->sole();
        $this->assertTrue($deposit->member->is($member));
        $this->assertSame($staff->id, $deposit->submitted_by);
        $this->assertSame(DepositStatus::Pending, $deposit->status);
        $this->assertSame(1, Activity::query()->where('description', 'deposit_recorded')->count());
    }

    public function test_recording_for_another_member_requires_create_for_others()
    {
        $actor = $this->activeMember();
        $other = $this->activeMember();

        $this->actingAs($actor->user)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $other->id]))
            ->assertForbidden();

        $this->assertSame(0, Deposit::query()->count());
    }

    public function test_staff_may_record_for_a_leaver_only_in_months_they_lived_here()
    {
        // Inactive factory member stayed 18 Jul – 18 Sep 2026.
        $leaver = Member::factory()->inactive()->create();
        $staff = User::factory()->withPermissions(Permission::DepositCreateForOthers)->create();

        $this->actingAs($staff)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $leaver->id, 'deposit_date' => '2026-10-02']))
            ->assertSessionHasErrors('deposit_date');

        $this->actingAs($staff)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $leaver->id, 'deposit_date' => '2026-09-25']))
            ->assertSessionHasNoErrors();
    }

    public function test_approve_on_save_approves_in_the_same_step()
    {
        $member = $this->activeMember();
        $staff = User::factory()->withPermissions(Permission::DepositCreateForOthers, Permission::DepositApprove)->create();

        $this->actingAs($staff)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $member->id, 'approve' => true]))
            ->assertSessionHasNoErrors();

        $deposit = Deposit::query()->sole();
        $this->assertSame(DepositStatus::Approved, $deposit->status);
        $this->assertFalse($deposit->is_self_approved);
        $this->assertFundMatchesLedger('2500.00');
    }

    public function test_approve_on_save_needs_deposit_approve()
    {
        $member = $this->activeMember();
        $staff = User::factory()->withPermissions(Permission::DepositCreateForOthers)->create();

        $this->actingAs($staff)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $member->id, 'approve' => true]))
            ->assertForbidden();

        $this->assertSame(0, Deposit::query()->count());
        $this->assertFundMatchesLedger('0.00');
    }
}
