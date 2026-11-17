<?php

namespace Tests\Feature\Deposits;

use App\Actions\Deposits\ApproveDeposit;
use App\Enums\DepositStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithDeposits;
use Tests\TestCase;

class DepositApprovalTest extends TestCase
{
    use RefreshDatabase, WorksWithDeposits;

    public function test_approval_credits_the_fund_once_on_the_deposits_own_date()
    {
        $reviewer = $this->reviewer();
        $deposit = Deposit::factory()->create(['amount' => '3000.50', 'deposit_date' => '2026-10-15']);

        $this->actingAs($reviewer)
            ->post(route('deposits.approve', $deposit))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('deposits.show', $deposit));

        $deposit->refresh();
        $this->assertSame(DepositStatus::Approved, $deposit->status);
        $this->assertTrue($deposit->reviewer->is($reviewer));
        $this->assertNotNull($deposit->reviewed_at);

        $entry = LedgerEntry::query()->sole();
        $this->assertSame(LedgerEntryType::Deposit, $entry->type);
        $this->assertSame(LedgerDirection::Credit, $entry->direction);
        $this->assertSame('3000.50', $entry->amount);
        $this->assertSame('2026-10-15', $entry->entry_date->toDateString());
        $this->assertTrue($entry->source->is($deposit));
        $this->assertFundMatchesLedger('3000.50');

        $activity = Activity::query()->where('description', 'deposit_approved')->sole();
        $this->assertSame('0.00', $activity->attribute_changes['old']['balance']);
        $this->assertSame('3000.50', $activity->attribute_changes['attributes']['balance']);
        $this->assertFalse($activity->properties['self_approved']);
    }

    public function test_repeated_approve_requests_post_only_once()
    {
        $reviewer = $this->reviewer();
        $deposit = Deposit::factory()->create();

        $this->actingAs($reviewer)->post(route('deposits.approve', $deposit))->assertRedirect();
        $this->actingAs($reviewer)->post(route('deposits.approve', $deposit))->assertForbidden();

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger($deposit->amount);
    }

    public function test_a_stale_copy_cannot_approve_twice()
    {
        // Two requests that both loaded the deposit while it was pending: the
        // second one re-reads the row under the lock and is refused.
        $reviewer = $this->reviewer();
        $deposit = Deposit::factory()->create();
        $staleCopy = Deposit::query()->findOrFail($deposit->id);

        app(ApproveDeposit::class)->handle($reviewer, $deposit);

        try {
            app(ApproveDeposit::class)->handle($reviewer, $staleCopy);
            $this->fail('The second approval should be refused.');
        } catch (AuthorizationException) {
            $this->assertSame(1, LedgerEntry::query()->count());
            $this->assertFundMatchesLedger($deposit->amount);
        }
    }

    public function test_a_late_approval_counts_in_the_deposits_month()
    {
        $deposit = Deposit::factory()->create(['deposit_date' => '2026-10-30']);
        $this->travelTo(now()->setDate(2026, 11, 2));

        $this->actingAs($this->reviewer())->post(route('deposits.approve', $deposit))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-30', LedgerEntry::query()->sole()->entry_date->toDateString());
    }

    public function test_approval_is_refused_while_the_deposits_month_is_closed()
    {
        $deposit = Deposit::factory()->create(['deposit_date' => '2026-09-30']);
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($this->reviewer())
            ->post(route('deposits.approve', $deposit))
            ->assertSessionHasErrors('deposit_date');

        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_approval_requires_deposit_approve()
    {
        $deposit = Deposit::factory()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::DepositViewAll)->create())
            ->post(route('deposits.approve', $deposit))
            ->assertForbidden();

        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
    }

    public function test_a_cancelled_or_rejected_deposit_cannot_be_approved()
    {
        $reviewer = $this->reviewer();

        foreach ([Deposit::factory()->cancelled()->create(), Deposit::factory()->rejected()->create()] as $deposit) {
            $this->actingAs($reviewer)->post(route('deposits.approve', $deposit))->assertForbidden();
        }

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_the_review_queue_lists_pending_deposits_for_reviewers()
    {
        Deposit::factory()->count(2)->create();
        Deposit::factory()->approved()->create();

        $this->actingAs($this->reviewer())
            ->get(route('approvals.deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('approvals/deposits/index')
                ->has('deposits', 2)
                ->where('nav.counts.pendingApprovals', 2));

        $this->actingAs($this->activeMember()->user)
            ->get(route('approvals.deposits.index'))
            ->assertForbidden();
    }

    public function test_each_queue_row_says_whether_the_viewer_may_approve_it()
    {
        $reviewer = $this->reviewer();
        $others = Deposit::factory()->create();
        $ownMember = Member::factory()->active()->create(['user_id' => $reviewer->id]);
        $own = Deposit::factory()->create(['member_id' => $ownMember->id]);

        $this->actingAs($reviewer)->get(route('approvals.deposits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deposits', fn ($rows) => collect($rows)->firstWhere('id', $others->id)['can_approve'] === true
                    && collect($rows)->firstWhere('id', $own->id)['can_approve'] === false));
    }

    public function test_the_approvals_entry_point_opens_the_first_queue_the_user_reviews()
    {
        $this->actingAs($this->reviewer())
            ->get(route('approvals.index'))
            ->assertRedirect(route('approvals.deposits.index'));

        $this->actingAs(User::factory()->withPermissions(Permission::MemberApproveRegistration)->create())
            ->get(route('approvals.index'))
            ->assertRedirect(route('approvals.registrations.index'));

        $this->actingAs($this->activeMember()->user)
            ->get(route('approvals.index'))
            ->assertForbidden();
    }
}
