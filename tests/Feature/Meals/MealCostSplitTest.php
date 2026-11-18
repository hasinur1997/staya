<?php

namespace Tests\Feature\Meals;

use App\Actions\Reports\CalculateMonthlyReport;
use App\Actions\Reports\CloseMonth;
use App\Actions\Reports\ReopenMonth;
use App\Concerns\BusinessCalendar;
use App\Enums\CostSplit;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MealCostSplitTest extends TestCase
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
        MemberStay::factory()->for($member)->between('2026-08-01')->create();

        return $member;
    }

    private function spend(string $amount, string $date = '2026-09-10'): void
    {
        GroceryPurchase::factory()->create(['buyer_member_id' => Member::query()->first()->id, 'grand_total' => $amount, 'purchase_date' => $date]);
    }

    private function eat(Member $member, int $count, string $date = '2026-09-10'): void
    {
        Meal::factory()->create(['member_id' => $member->id, 'meal_type_id' => MealType::query()->first()->id, 'meal_date' => $date, 'count' => $count]);
    }

    private function useMeals(): void
    {
        MessSetting::current()->forceFill(['cost_split' => CostSplit::Meals])->save();
    }

    private function report(string $month = '2026-09')
    {
        return app(CalculateMonthlyReport::class)->handle((int) substr($month, 0, 4), (int) substr($month, 5, 2));
    }

    public function test_the_default_is_an_equal_split()
    {
        $a = $this->member();
        $b = $this->member();
        $this->spend('1000.00');
        $this->eat($a, 9);
        $this->eat($b, 1);

        $report = $this->report();

        $this->assertSame(CostSplit::Equal, $report->split);
        $this->assertSame('500.00', $report->share);
        $this->assertSame('500.00', $report->rowFor($a->id)['share']);
        $this->assertSame('500.00', $report->rowFor($b->id)['share']);
    }

    public function test_the_meal_split_divides_the_cost_by_each_members_meals()
    {
        $a = $this->member();
        $b = $this->member();
        $this->spend('1000.00');
        $this->eat($a, 3);
        $this->eat($b, 1);
        Deposit::factory()->approved()->create(['member_id' => $a->id, 'amount' => '900.00', 'deposit_date' => '2026-09-05']);
        $this->useMeals();

        $report = $this->report();

        $this->assertSame(CostSplit::Meals, $report->split);
        $this->assertSame(4, $report->totalMeals);
        $this->assertSame('250.00', $report->share);
        $this->assertSame('750.00', $report->rowFor($a->id)['share']);
        $this->assertSame('250.00', $report->rowFor($b->id)['share']);
        $this->assertSame('150.00', $report->rowFor($a->id)['due_credit']);
        $this->assertSame('-250.00', $report->rowFor($b->id)['due_credit']);
        $this->assertSame('0.00', $report->leftover);
    }

    public function test_paisa_that_do_not_divide_evenly_are_reported_as_leftover()
    {
        $members = [$this->member(), $this->member(), $this->member()];
        $this->spend('100.00');

        foreach ($members as $member) {
            $this->eat($member, 1);
        }

        $this->useMeals();
        $report = $this->report();

        $this->assertSame('33.33', $report->share);

        foreach ($members as $member) {
            $this->assertSame('33.33', $report->rowFor($member->id)['share']);
        }

        $this->assertSame('0.01', $report->leftover);
    }

    public function test_a_month_without_meals_charges_nobody_and_leaves_the_cost_uncovered()
    {
        $a = $this->member();
        $this->spend('400.00');
        $this->useMeals();

        $report = $this->report();

        $this->assertSame(0, $report->totalMeals);
        $this->assertSame('0.00', $report->share);
        $this->assertSame('0.00', $report->rowFor($a->id)['share']);
        $this->assertSame('400.00', $report->leftover);
    }

    public function test_a_member_with_meals_but_no_stay_in_the_period_is_still_counted()
    {
        $a = $this->member();
        $ghost = Member::factory()->active(withStay: false)->create();
        $this->spend('300.00');
        $this->eat($a, 1);
        $this->eat($ghost, 2);
        $this->useMeals();

        $report = $this->report();

        $this->assertSame('200.00', $report->rowFor($ghost->id)['share']);
        $this->assertSame('100.00', $report->rowFor($a->id)['share']);
        $this->assertSame('0.00', $report->leftover);
    }

    public function test_a_closed_month_keeps_the_split_it_was_closed_with_until_it_is_reopened()
    {
        $a = $this->member();
        $b = $this->member();
        $this->spend('1000.00');
        $this->eat($a, 3);
        $this->eat($b, 1);
        $closer = User::factory()->create();

        app(CloseMonth::class)->handle($closer, '2026-09');
        $this->useMeals();

        $this->assertSame(CostSplit::Equal, $this->report('2026-09')->split);
        $this->assertSame('500.00', $this->report('2026-09')->rowFor($a->id)['share']);
        $this->assertSame(CostSplit::Meals, $this->report('2026-10')->split);

        app(ReopenMonth::class)->handle($closer, '2026-09', 'Fix a meal');
        $this->assertSame(CostSplit::Meals, $this->report('2026-09')->split);
        $this->assertSame('750.00', $this->report('2026-09')->rowFor($a->id)['share']);

        app(CloseMonth::class)->handle($closer, '2026-09');
        MessSetting::current()->forceFill(['cost_split' => CostSplit::Equal])->save();
        $this->assertSame(CostSplit::Meals, $this->report('2026-09')->split);
    }

    public function test_the_report_page_and_dashboard_expose_the_split_and_meal_counts()
    {
        $a = $this->member();
        $this->spend('600.00');
        $this->eat($a, 3);
        $this->useMeals();
        $viewer = User::factory()->withPermissions(Permission::ReportViewAll, Permission::ReportViewOwn)->create();

        $this->actingAs($viewer)->get(route('reports.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.figures.split', 'meals')
                ->where('report.figures.total_meals', 3)
                ->where('report.figures.share', '200.00')
                ->where('report.members.0.meals', 3));

        $this->actingAs($a->user)->get(route('dashboard', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.meals', 3)
                ->where('summary.share', '600.00')
                ->where('report.split', 'meals'));
    }

    public function test_only_users_who_can_change_settings_can_choose_the_split_and_the_change_is_logged()
    {
        $payload = [
            'mess_name' => 'Mess', 'mess_address' => null, 'opening_balance' => '0', 'fund_start_date' => null,
            'grocery_edit_window_minutes' => 30, 'members_can_view_balance' => true, 'max_upload_kb' => 5120, 'default_locale' => 'en',
        ];
        $viewer = User::factory()->withPermissions(Permission::SettingsView)->create();
        $admin = User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsUpdate)->create();

        $this->actingAs($viewer)->put(route('mess-settings.update'), [...$payload, 'cost_split' => 'meals'])->assertForbidden();
        $this->assertSame(CostSplit::Equal, MessSetting::current()->fresh()->cost_split);

        $this->actingAs($admin)->put(route('mess-settings.update'), [...$payload, 'cost_split' => 'nonsense'])->assertSessionHasErrors('cost_split');
        $this->actingAs($admin)->put(route('mess-settings.update'), [...$payload, 'cost_split' => 'meals'])->assertSessionHasNoErrors();

        $this->assertSame(CostSplit::Meals, MessSetting::current()->fresh()->cost_split);
        $changes = Activity::query()->where('description', 'settings_updated')->latest('id')->first()->attribute_changes;
        $this->assertSame('equal', $changes['old']['cost_split']);
        $this->assertSame('meals', $changes['attributes']['cost_split']);
    }
}
