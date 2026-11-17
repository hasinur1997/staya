<?php

namespace Tests\Feature\Dashboard;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\MessSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    private function member(): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between('2026-09-01')->create();

        return $member;
    }

    public function test_a_member_sees_their_own_summary_for_the_month()
    {
        $member = $this->member();
        $other = $this->member();
        GroceryPurchase::factory()->create(['buyer_member_id' => $other->id, 'grand_total' => '1000.00', 'purchase_date' => '2026-10-10']);
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '800.00', 'deposit_date' => '2026-10-05']);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('month', '2026-10')
                ->where('summary.deposits', '800.00')
                ->where('summary.share', '500.00')
                ->where('summary.due_credit', '300.00')
                ->where('report.expenses', '1000.00')
                ->where('report.active_members', 2)
                ->has('report.members', 2));
    }

    public function test_a_member_sees_deposit_statuses_with_reasons_and_what_the_fund_owes_them()
    {
        $member = $this->member();
        Deposit::factory()->rejected('Receipt does not match.')->create(['member_id' => $member->id, 'deposit_date' => '2026-10-06']);
        $purchase = GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '400.00']);
        BuyerPayable::factory()->create(['grocery_purchase_id' => $purchase->id, 'member_id' => $member->id, 'amount' => '400.00', 'amount_reimbursed' => '150.00']);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentDeposits.0.status', 'rejected')
                ->where('recentDeposits.0.rejection_reason', 'Receipt does not match.')
                ->where('owed', '250.00'));
    }

    public function test_the_balance_is_not_sent_when_the_setting_hides_it()
    {
        $member = $this->member();

        MessSetting::current()->update(['members_can_view_balance' => false]);
        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('fund.balance', null));

        MessSetting::current()->update(['members_can_view_balance' => true]);
        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->whereType('fund.balance', 'string'));
    }

    public function test_own_purchases_show_the_countdown_inside_the_window_and_locked_after_it()
    {
        $member = $this->member();
        $purchase = GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'purchase_date' => '2026-10-17']);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentPurchases.0.id', $purchase->id)
                ->whereType('recentPurchases.0.window_closes_at', 'string')
                ->where('recentPurchases.0.locked', false));

        $this->travel(31)->minutes();

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentPurchases.0.window_closes_at', null)
                ->where('recentPurchases.0.locked', true));
    }

    public function test_an_inactive_member_gets_a_read_only_dashboard_and_403_on_create_endpoints()
    {
        $member = Member::factory()->inactive()->create();

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('memberStatus', 'inactive')
                ->where('can.createDeposit', false)
                ->where('can.createPurchase', false));

        $this->actingAs($member->user)->get(route('deposits.create'))->assertForbidden();
        $this->actingAs($member->user)->post(route('deposits.store'), ['amount' => '100.00', 'deposit_date' => '2026-10-17'])->assertForbidden();
        $this->actingAs($member->user)->get(route('grocery.create'))->assertForbidden();
    }

    public function test_an_active_member_is_offered_the_create_links()
    {
        $this->actingAs($this->member()->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('can.createDeposit', true)->where('can.createPurchase', true));
    }

    public function test_pending_and_rejected_applicants_cannot_reach_the_dashboard()
    {
        $pending = Member::factory()->create();
        $rejected = Member::factory()->rejected()->create();

        $this->actingAs($pending->user)->get(route('dashboard'))->assertRedirect(route('registration.status'));
        $this->actingAs($rejected->user)->get(route('dashboard'))->assertRedirect(route('registration.status'));
    }

    public function test_without_report_permissions_the_figures_are_not_sent()
    {
        $member = $this->member();
        $member->user->syncRoles([Role::factory()->withPermissions(Permission::DepositViewOwn)->create()]);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('summary', null)->where('report', null));
    }

    public function test_viewing_only_ones_own_report_hides_the_member_table()
    {
        $member = $this->member();
        $member->user->syncRoles([Role::factory()->withPermissions(Permission::ReportViewOwn)->create()]);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->whereType('summary', 'array')->where('report', null));
    }

    public function test_staff_see_the_queues_they_may_review_and_members_do_not()
    {
        Deposit::factory()->count(2)->create(['member_id' => $this->member()->id]);
        Member::factory()->create();
        BuyerPayable::factory()->create();
        $manager = User::factory()->withPermissions(Permission::DepositViewAll, Permission::DepositApprove, Permission::MemberView, Permission::MemberApproveRegistration, Permission::LedgerReimburse)->create();

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('queues.pendingDeposits', 2)
                ->where('queues.pendingRegistrations', 1)
                ->where('queues.openPayables', 1));

        $this->actingAs($this->member()->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('queues.pendingDeposits', null)
                ->where('queues.pendingRegistrations', null)
                ->where('queues.openPayables', null));
    }

    public function test_the_month_can_be_browsed_but_not_beyond_the_current_one()
    {
        $member = $this->member();
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '300.00', 'deposit_date' => '2026-09-12']);

        $this->actingAs($member->user)->get(route('dashboard', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-09')->where('summary.deposits', '300.00'));

        $this->actingAs($member->user)->get(route('dashboard', ['month' => '2027-03']))
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-10'));

        $this->actingAs($member->user)->get(route('dashboard', ['month' => 'nonsense']))
            ->assertSessionHasErrors('month');
    }
}
