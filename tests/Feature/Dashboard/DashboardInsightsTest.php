<?php

namespace Tests\Feature\Dashboard;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use App\Models\LedgerEntry;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class DashboardInsightsTest extends TestCase
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
        MemberStay::factory()->for($member)->between('2026-05-01')->create();

        return $member;
    }

    private function viewer(): User
    {
        return User::factory()->withPermissions(Permission::ReportViewOwn, Permission::ReportViewAll)->create();
    }

    public function test_report_viewers_get_six_months_of_trend_with_fund_balance_at_each_month_end()
    {
        LedgerEntry::factory()->openingBalance()->create(['entry_date' => '2026-08-01', 'amount' => '1000.00']);
        LedgerEntry::factory()->debit()->create(['entry_date' => '2026-09-10', 'amount' => '300.00']);
        LedgerEntry::factory()->credit()->create(['entry_date' => '2026-10-05', 'amount' => '50.50']);
        LedgerEntry::factory()->credit()->create(['entry_date' => '2026-11-02', 'amount' => '9999.00']);

        $this->actingAs($this->viewer())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('insights.trend', 6)
                ->where('insights.trend.0.month', '2026-05')
                ->where('insights.trend.0.fund_end', '0.00')
                ->where('insights.trend.3.fund_end', '1000.00')
                ->where('insights.trend.4.fund_end', '700.00')
                ->where('insights.trend.5.month', '2026-10')
                ->where('insights.trend.5.fund_end', '750.50')
                ->where('insights.changes.fund', '50.50'));
    }

    public function test_the_trend_follows_the_month_being_viewed()
    {
        LedgerEntry::factory()->openingBalance()->create(['entry_date' => '2026-08-01', 'amount' => '1000.00']);

        $this->actingAs($this->viewer())->get(route('dashboard', ['month' => '2026-08']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('insights.trend.5.month', '2026-08')
                ->where('insights.trend.5.fund_end', '1000.00')
                ->where('insights.changes.fund', '1000.00'));
    }

    public function test_daily_spending_meals_and_top_items_cover_the_month()
    {
        $member = $this->member();
        $first = GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '500.00', 'purchase_date' => '2026-10-03']);
        GroceryPurchaseItem::query()->where('grocery_purchase_id', $first->id)->update(['item_name' => 'Rice', 'line_total' => '300.00']);
        GroceryPurchaseItem::factory()->for($first, 'purchase')->create(['item_name' => 'rice ', 'line_total' => '200.00']);
        $second = GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '120.00', 'purchase_date' => '2026-10-03']);
        GroceryPurchaseItem::query()->where('grocery_purchase_id', $second->id)->update(['item_name' => 'Eggs', 'line_total' => '120.00']);
        GroceryPurchase::factory()->voided()->create(['buyer_member_id' => $member->id, 'grand_total' => '9000.00', 'purchase_date' => '2026-10-03']);

        $lunch = MealType::factory()->create();
        Meal::factory()->create(['member_id' => $member->id, 'meal_type_id' => $lunch->id, 'meal_date' => '2026-10-03', 'count' => 3]);

        $this->actingAs($this->viewer())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('insights.daily', 31)
                ->where('insights.daily.2.expenses', '620.00')
                ->where('insights.daily.2.meals', 3)
                ->where('insights.daily.3.expenses', '0.00')
                ->where('insights.top_items.0', ['name' => 'Rice', 'total' => '500.00'])
                ->where('insights.top_items.1', ['name' => 'Eggs', 'total' => '120.00']));
    }

    public function test_monthly_changes_compare_with_the_month_before()
    {
        $member = $this->member();
        GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '400.00', 'purchase_date' => '2026-09-10']);
        GroceryPurchase::factory()->create(['buyer_member_id' => $member->id, 'grand_total' => '1000.00', 'purchase_date' => '2026-10-10']);

        $this->actingAs($this->viewer())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('insights.changes.expenses', '600.00'));
    }

    public function test_members_get_their_own_trend_and_users_without_reports_get_nothing()
    {
        $member = $this->member();
        Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => '800.00', 'deposit_date' => '2026-10-05']);

        $this->actingAs($member->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('myTrend.5.month', '2026-10')
                ->where('myTrend.5.deposits', '800.00'));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('insights', null)->where('myTrend', null));
    }
}
