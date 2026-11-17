<?php

namespace Tests\Feature\Deposits;

use App\Enums\DepositStatus;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorksWithDeposits;
use Tests\TestCase;

class SelfApprovalTest extends TestCase
{
    use RefreshDatabase, WorksWithDeposits;

    private function reviewerWhoIsAMember(Permission ...$extra): Member
    {
        $member = $this->activeMember();
        $member->user->givePermissionTo([Permission::DepositApprove->value, ...array_map(fn (Permission $permission) => $permission->value, $extra)]);

        return $member;
    }

    public function test_a_reviewer_cannot_approve_their_own_deposit_without_approve_own()
    {
        $member = $this->reviewerWhoIsAMember();
        $deposit = Deposit::factory()->create(['member_id' => $member->id]);

        $this->actingAs($member->user)
            ->post(route('deposits.approve', $deposit))
            ->assertForbidden();

        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_with_approve_own_the_self_approval_is_flagged()
    {
        $member = $this->reviewerWhoIsAMember(Permission::DepositApproveOwn);
        $deposit = Deposit::factory()->create(['member_id' => $member->id]);

        $this->actingAs($member->user)
            ->post(route('deposits.approve', $deposit))
            ->assertSessionHasNoErrors();

        $this->assertTrue($deposit->fresh()->is_self_approved);
        $this->assertTrue(Activity::query()->where('description', 'deposit_approved')->sole()->properties['self_approved']);
        $this->assertFundMatchesLedger($deposit->amount);
    }

    public function test_approving_someone_elses_deposit_is_not_flagged()
    {
        $member = $this->reviewerWhoIsAMember(Permission::DepositApproveOwn);
        $deposit = Deposit::factory()->create();

        $this->actingAs($member->user)->post(route('deposits.approve', $deposit));

        $this->assertFalse($deposit->fresh()->is_self_approved);
    }

    public function test_approve_on_save_for_ones_own_deposit_follows_the_same_rule()
    {
        $member = $this->reviewerWhoIsAMember();

        $this->actingAs($member->user)
            ->post(route('deposits.store'), $this->depositForm(['approve' => true]))
            ->assertForbidden();

        $this->assertSame(0, Deposit::query()->count());

        $member->user->givePermissionTo(Permission::DepositApproveOwn->value);

        $this->actingAs($member->user)
            ->post(route('deposits.store'), $this->depositForm(['approve' => true]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Deposit::query()->sole()->is_self_approved);
    }

    public function test_recording_and_approving_for_another_member_is_not_self_approval()
    {
        $staff = User::factory()->withPermissions(Permission::DepositCreateForOthers, Permission::DepositApprove)->create();
        $member = $this->activeMember();

        $this->actingAs($staff)
            ->post(route('deposits.store'), $this->depositForm(['member_id' => $member->id, 'approve' => true]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(Deposit::query()->sole()->is_self_approved);
    }
}
