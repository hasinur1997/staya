<?php

namespace Tests\Feature\Dashboard;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class MemberAccountGlanceTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl, WorksWithPurchases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    private function member(): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between('2026-08-01')->create();

        return $member;
    }

    public function test_a_member_sees_total_deposited_total_bajar_and_the_fund_balance()
    {
        $member = $this->member();
        $other = $this->member();
        $this->fund('5000.00', '2026-08-01');

        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '1000.50', 'deposit_date' => '2026-09-05']);
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '700.25', 'deposit_date' => '2026-10-05']);
        Deposit::factory()->create(['member_id' => $member->id, 'amount' => '999.00', 'deposit_date' => '2026-10-06']);
        Deposit::factory()->rejected()->create(['member_id' => $member->id, 'amount' => '888.00', 'deposit_date' => '2026-10-07']);
        Deposit::factory()->approved()->create(['member_id' => $other->id, 'amount' => '5000.00', 'deposit_date' => '2026-10-08']);

        GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '300.10', 'purchase_date' => '2026-09-10']);
        GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '450.20', 'purchase_date' => '2026-10-10']);
        GroceryPurchase::factory()->voided()->create(['buyer_member_id' => $member->id, 'grand_total' => '777.00', 'purchase_date' => '2026-10-11']);
        GroceryPurchase::factory()->create(['buyer_member_id' => $other->id, 'grand_total' => '600.00', 'purchase_date' => '2026-10-12']);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.deposited.total', '1700.75')
                ->where('account.deposited.month', '700.25')
                ->where('account.bajar.total', '750.30')
                ->where('account.bajar.month', '450.20')
                ->whereType('fund.balance', 'string'));
    }

    public function test_the_figures_follow_the_chosen_month()
    {
        $member = $this->member();
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '1000.00', 'deposit_date' => '2026-09-05']);
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '200.00', 'deposit_date' => '2026-10-05']);

        $this->actingAs($member->user)->get(route('dashboard', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.deposited.total', '1200.00')
                ->where('account.deposited.month', '1000.00'));
    }

    public function test_a_member_with_nothing_yet_sees_zeros()
    {
        $this->actingAs($this->member()->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.deposited.total', '0.00')
                ->where('account.bajar.total', '0.00'));
    }

    public function test_totals_are_hidden_without_the_matching_access_and_for_users_without_a_member_profile()
    {
        $member = $this->member();
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '1000.00', 'deposit_date' => '2026-10-05']);
        $member->user->syncRoles([]);
        $member->user->givePermissionTo(Permission::GroceryView, Permission::ReportViewOwn);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('account.deposited', null)->has('account.bajar'));

        $this->actingAs(User::factory()->withPermissions(Permission::ReportViewAll)->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('account', null));
    }
}
