<?php

namespace Tests\Feature\Reports;

use App\Actions\Ledger\PostAdjustment;
use App\Concerns\BusinessCalendar;
use App\Enums\LedgerDirection;
use App\Enums\Permission;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 11, 18)->setTime(12, 0));
    }

    private function member(): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between('2026-09-01')->create();

        return $member;
    }

    private function limited(Member $member, Permission ...$permissions): User
    {
        $member->user->syncRoles([Role::factory()->withPermissions(...$permissions)->create()]);

        return $member->user;
    }

    public function test_the_report_shows_opening_and_closing_balance_deposits_expenses_and_each_members_due_or_credit()
    {
        $a = $this->member();
        $b = $this->member();
        app(PostAdjustment::class)->handle(User::factory()->create(), ['direction' => LedgerDirection::Credit, 'amount' => '1000.00', 'entry_date' => '2026-09-20', 'reason' => 'Opening cash']);
        Deposit::factory()->approved()->create(['member_id' => $a->id, 'amount' => '3000.00', 'deposit_date' => '2026-10-05']);
        GroceryPurchase::factory()->settled()->create(['buyer_member_id' => $b->id, 'grand_total' => '2000.00', 'purchase_date' => '2026-10-10']);

        $this->actingAs($this->limited($a, Permission::ReportViewAll, Permission::LedgerViewBalance))
            ->get(route('reports.index', ['month' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->where('report.month', '2026-10')
                ->where('report.from', '2026-10-01')
                ->where('report.to', '2026-10-31')
                ->where('report.figures.deposits', '3000.00')
                ->where('report.figures.expenses', '2000.00')
                ->where('report.figures.active_members', 2)
                ->where('report.figures.share', '1000.00')
                ->where('report.ledger.opening', '1000.00')
                ->where('report.ledger.closing', '2000.00')
                ->where('report.members.0.due_credit', '2000.00')
                ->where('report.members.1.due_credit', '-1000.00')
                ->where('report.closure.status', 'open'));
    }

    public function test_viewing_only_ones_own_report_shows_one_row_and_hides_other_members_data()
    {
        $a = $this->member();
        $b = $this->member();
        Deposit::factory()->create(['member_id' => $b->id]);
        Deposit::factory()->approved()->create(['member_id' => $b->id, 'is_self_approved' => true, 'deposit_date' => '2026-10-05']);

        $this->actingAs($this->limited($a, Permission::ReportViewOwn))
            ->get(route('reports.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.members', 1)
                ->where('report.members.0.member_id', $a->id)
                ->where('report.unapproved', null)
                ->where('report.self_approved', null)
                ->where('report.ledger', null)
                ->where('report.open_payables', null)
                ->where('can.viewAll', false)
                ->where('members', []));
    }

    public function test_pending_and_rejected_summaries_self_approved_deposits_and_open_payables_are_reported()
    {
        $a = $this->member();
        Deposit::factory()->create(['member_id' => $a->id, 'amount' => '400.00', 'deposit_date' => '2026-10-06']);
        Deposit::factory()->rejected()->create(['member_id' => $a->id, 'amount' => '250.00', 'deposit_date' => '2026-10-07']);
        $self = Deposit::factory()->approved()->create(['member_id' => $a->id, 'amount' => '900.00', 'deposit_date' => '2026-10-08', 'is_self_approved' => true]);
        BuyerPayable::factory()->create(['amount' => '300.00', 'amount_reimbursed' => '100.00']);
        $viewer = User::factory()->withPermissions(Permission::ReportViewAll, Permission::LedgerReimburse)->create();

        $this->actingAs($viewer)->get(route('reports.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.unapproved.pending', ['count' => 1, 'amount' => '400.00'])
                ->where('report.unapproved.rejected', ['count' => 1, 'amount' => '250.00'])
                ->where('report.self_approved.0.id', $self->id)
                ->where('report.open_payables', ['count' => 1, 'amount' => '200.00']));
    }

    public function test_a_custom_range_and_a_member_filter_narrow_the_report()
    {
        $a = $this->member();
        $b = $this->member();
        Deposit::factory()->approved()->create(['member_id' => $a->id, 'amount' => '500.00', 'deposit_date' => '2026-10-20']);
        Deposit::factory()->approved()->create(['member_id' => $a->id, 'amount' => '700.00', 'deposit_date' => '2026-10-02']);
        $viewer = User::factory()->withPermissions(Permission::ReportViewAll)->create();

        $this->actingAs($viewer)->get(route('reports.index', ['from' => '2026-10-15', 'to' => '2026-10-25', 'member' => $b->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.month', null)
                ->where('report.closure', null)
                ->where('report.figures.deposits', '500.00')
                ->has('report.members', 1)
                ->where('report.members.0.member_id', $b->id));
    }

    public function test_invalid_periods_are_rejected()
    {
        $viewer = User::factory()->withPermissions(Permission::ReportViewAll)->create();

        $this->actingAs($viewer)->get(route('reports.index', ['month' => 'October']))->assertSessionHasErrors('month');
        $this->actingAs($viewer)->get(route('reports.index', ['from' => '2026-10-20', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->actingAs($viewer)->get(route('reports.index', ['from' => '2024-01-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
        $this->actingAs($viewer)->get(route('reports.index', ['from' => '2026-10-01']))->assertSessionHasErrors('to');
    }

    public function test_the_report_needs_a_report_permission()
    {
        $user = User::factory()->withPermissions(Permission::LedgerViewBalance)->create();

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_the_member_profile_shows_monthly_due_or_credit_to_the_member_and_to_report_viewers_only()
    {
        $member = $this->member();
        $stranger = $this->member();
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '800.00', 'deposit_date' => '2026-10-05']);
        GroceryPurchase::factory()->create(['buyer_member_id' => $stranger->id, 'grand_total' => '1000.00', 'purchase_date' => '2026-10-10']);

        $this->actingAs($member->user)->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthly.0.month', '2026-11')
                ->where('monthly.1.month', '2026-10')
                ->where('monthly.1.deposits', '800.00')
                ->where('monthly.1.share', '500.00')
                ->where('monthly.1.due_credit', '300.00'));

        $viewer = User::factory()->withPermissions(Permission::MemberView, Permission::ReportViewAll)->create();
        $this->actingAs($viewer)->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->whereType('monthly', 'array'));

        $noReports = User::factory()->withPermissions(Permission::MemberView)->create();
        $this->actingAs($noReports)->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->where('monthly', null));

        $this->actingAs($this->limited($stranger, Permission::MemberView, Permission::ReportViewOwn))
            ->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->where('monthly', null));
    }
}
