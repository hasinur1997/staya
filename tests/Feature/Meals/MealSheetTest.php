<?php

namespace Tests\Feature\Meals;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\GroceryPurchase;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MealSheetTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private MealType $lunch;

    private MealType $dinner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
        MealType::query()->delete();
        $this->lunch = MealType::factory()->create(['name' => 'Lunch', 'sort_order' => 1]);
        $this->dinner = MealType::factory()->create(['name' => 'Dinner', 'sort_order' => 2]);
    }

    private function member(): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between('2026-08-01')->create();

        return $member;
    }

    private function eat(Member $member, MealType $type, string $date, int $count): void
    {
        Meal::factory()->create(['member_id' => $member->id, 'meal_type_id' => $type->id, 'meal_date' => $date, 'count' => $count]);
    }

    private function viewer(): User
    {
        return User::factory()->withPermissions(Permission::MealViewAll)->create();
    }

    public function test_the_sheet_needs_view_all_and_guests_are_sent_to_login()
    {
        $member = $this->member();

        $this->actingAs(User::factory()->create())->get(route('meals.sheet'))->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(Permission::MealViewOwn)->create())->get(route('meals.sheet'))->assertForbidden();
        $this->actingAs($member->user)->get(route('meals.sheet'))->assertForbidden();

        auth()->logout();
        $this->get(route('meals.sheet'))->assertRedirect(route('login'));
    }

    public function test_the_sheet_has_a_column_for_every_day_and_totals_for_rows_and_columns()
    {
        $a = $this->member();
        $b = $this->member();
        $this->eat($a, $this->lunch, '2026-10-02', 1);
        $this->eat($a, $this->dinner, '2026-10-02', 2);
        $this->eat($a, $this->lunch, '2026-10-05', 1);
        $this->eat($b, $this->lunch, '2026-10-02', 1);
        $this->eat($b, $this->lunch, '2026-09-30', 4);
        GroceryPurchase::factory()->create(['buyer_member_id' => $a->id, 'grand_total' => '500.00', 'purchase_date' => '2026-10-03']);

        $this->actingAs($this->viewer())->get(route('meals.sheet'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('meals/sheet')
                ->where('month', '2026-10')
                ->has('days', 31)
                ->where('days.0.weekday', 4)
                ->where('days.1.total', 4)
                ->where('days.4.total', 1)
                ->where('total', 5)
                ->where('figures.total_meals', 5)
                ->where('figures.rate', '100.00')
                ->where('rows', fn ($rows) => collect($rows)->keyBy('member_id')->map(fn ($row) => [$row['total'], $row['cost']])->sortKeys()->all() == collect([
                    $a->id => [4, '400.00'],
                    $b->id => [1, '100.00'],
                ])->sortKeys()->all()));
    }

    public function test_the_sheet_follows_the_month_and_knows_how_long_it_is()
    {
        $a = $this->member();
        $this->eat($a, $this->lunch, '2026-02-10', 2);

        $this->actingAs($this->viewer())->get(route('meals.sheet', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2026-02')
                ->has('days', 28)
                ->where('total', 2)
                ->where('rows.0.counts.10', 2));

        $this->actingAs($this->viewer())->get(route('meals.sheet', ['month' => '2027-01']))
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-10'));
    }

    public function test_the_sheet_can_be_limited_to_one_meal_type_and_then_leaves_cost_out()
    {
        $a = $this->member();
        $this->eat($a, $this->lunch, '2026-10-02', 1);
        $this->eat($a, $this->dinner, '2026-10-02', 2);

        $this->actingAs($this->viewer())->get(route('meals.sheet', ['type' => $this->dinner->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('typeId', $this->dinner->id)
                ->where('total', 2)
                ->where('figures.total_meals', 3)
                ->where('rows.0.total', 2)
                ->where('rows.0.cost', null));

        $this->actingAs($this->viewer())->get(route('meals.sheet', ['type' => 999999]))->assertSessionHasErrors('type');
        $this->actingAs($this->viewer())->get(route('meals.sheet', ['month' => 'nonsense']))->assertSessionHasErrors('month');
    }

    public function test_the_record_flag_lets_recorders_open_a_day()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::MealRecord)->create())->get(route('meals.sheet'))
            ->assertInertia(fn (Assert $page) => $page->where('can.record', true));

        $this->actingAs($this->viewer())->get(route('meals.sheet'))
            ->assertInertia(fn (Assert $page) => $page->where('can.record', false));
    }
}
