<?php

namespace Tests\Feature\Meals;

use App\Actions\Reports\CloseMonth;
use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\Activity;
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

class MealRecordingTest extends TestCase
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

    private function recorder(): User
    {
        return User::factory()->withPermissions(Permission::MealRecord)->create();
    }

    private function member(string $joined = '2026-09-01', ?string $left = null): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between($joined, $left)->create();

        return $member;
    }

    /**
     * @param  array<int, array<int, int>>  $entries
     * @return array<string, mixed>
     */
    private function form(string $date, array $entries): array
    {
        return ['date' => $date, 'entries' => $entries];
    }

    public function test_recording_needs_the_record_permission()
    {
        $member = $this->member();
        $viewer = User::factory()->withPermissions(Permission::MealViewAll, Permission::MealViewOwn)->create();

        $this->actingAs($viewer)->post(route('meals.store'), $this->form('2026-10-10', [$member->id => [$this->lunch->id => 1]]))->assertForbidden();
        $this->actingAs($member->user)->post(route('meals.store'), $this->form('2026-10-10', [$member->id => [$this->lunch->id => 1]]))->assertForbidden();

        $this->assertSame(0, Meal::query()->count());
    }

    public function test_the_day_screen_needs_view_all_and_own_only_users_are_sent_to_their_meals()
    {
        $member = $this->member();
        $stranger = User::factory()->create();
        $owner = User::factory()->withPermissions(Permission::MealViewOwn)->create();
        Member::factory()->active(withStay: false)->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->get(route('meals.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('meals.index'))->assertRedirect(route('meals.mine'));
    }

    public function test_a_day_of_meals_is_saved_and_logged_with_before_and_after_counts()
    {
        $a = $this->member();
        $b = $this->member();
        $staff = $this->recorder();

        $this->actingAs($staff)->post(route('meals.store'), $this->form('2026-10-10', [
            $a->id => [$this->lunch->id => 2, $this->dinner->id => 1],
            $b->id => [$this->lunch->id => 1, $this->dinner->id => 0],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(3, Meal::query()->count());
        $this->assertSame(2, Meal::query()->where('member_id', $a->id)->where('meal_type_id', $this->lunch->id)->sole()->count);
        $this->assertSame($staff->id, Meal::query()->first()->recorded_by);

        $activity = Activity::query()->where('description', 'meals_recorded')->sole();
        $this->assertSame('2026-10-10', $activity->properties['date']);
        $this->assertSame(2, $activity->attribute_changes['attributes'][$a->full_name.' / Lunch']);
        $this->assertSame(0, $activity->attribute_changes['old'][$a->full_name.' / Lunch']);
        $this->assertNotNull($activity->ip_address);
        $this->assertSame($staff->id, $activity->causer_id);
    }

    public function test_changing_a_count_updates_it_and_zero_removes_the_row()
    {
        $a = $this->member();
        $staff = $this->recorder();
        Meal::factory()->create(['member_id' => $a->id, 'meal_type_id' => $this->lunch->id, 'meal_date' => '2026-10-10', 'count' => 2]);
        Meal::factory()->create(['member_id' => $a->id, 'meal_type_id' => $this->dinner->id, 'meal_date' => '2026-10-10', 'count' => 1]);

        $this->actingAs($staff)->post(route('meals.store'), $this->form('2026-10-10', [$a->id => [$this->lunch->id => 3, $this->dinner->id => 0]]))->assertSessionHasNoErrors();

        $this->assertSame(3, Meal::query()->where('meal_type_id', $this->lunch->id)->sole()->count);
        $this->assertSame(0, Meal::query()->where('meal_type_id', $this->dinner->id)->count());
        $changes = Activity::query()->where('description', 'meals_recorded')->sole()->attribute_changes;
        $this->assertSame(2, $changes['old'][$a->full_name.' / Lunch']);
        $this->assertSame(0, $changes['attributes'][$a->full_name.' / Dinner']);
    }

    public function test_saving_the_same_counts_again_logs_nothing()
    {
        $a = $this->member();
        $staff = $this->recorder();
        $form = $this->form('2026-10-10', [$a->id => [$this->lunch->id => 1]]);

        $this->actingAs($staff)->post(route('meals.store'), $form);
        $this->actingAs($staff)->post(route('meals.store'), $form)->assertSessionHasNoErrors();

        $this->assertSame(1, Activity::query()->where('description', 'meals_recorded')->count());
    }

    public function test_invalid_meals_are_refused_and_nothing_is_saved()
    {
        $a = $this->member('2026-10-05');
        $left = $this->member('2026-09-01', '2026-10-08');
        $staff = $this->recorder();
        $inactive = MealType::factory()->inactive()->create();

        $post = fn (string $date, array $entries) => $this->actingAs($staff)->post(route('meals.store'), $this->form($date, $entries));

        $post('2026-10-19', [$a->id => [$this->lunch->id => 1]])->assertSessionHasErrors('date');
        $post('2026-10-03', [$a->id => [$this->lunch->id => 1]])->assertSessionHasErrors('entries');
        $post('2026-10-10', [$left->id => [$this->lunch->id => 1]])->assertSessionHasErrors('entries');
        $post('2026-10-10', [$a->id => [$inactive->id => 1]])->assertSessionHasErrors('entries');
        $post('2026-10-10', [$a->id => [$this->lunch->id => 11]])->assertSessionHasErrors('entries.'.$a->id.'.'.$this->lunch->id);
        $post('2026-10-10', [$a->id => [$this->lunch->id => -1]])->assertSessionHasErrors('entries.'.$a->id.'.'.$this->lunch->id);
        $post('2026-10-10', [$a->id => [999999 => 1]])->assertSessionHasErrors('entries');

        $this->assertSame(0, Meal::query()->count());
        $this->assertSame(0, Activity::query()->where('description', 'meals_recorded')->count());
    }

    public function test_a_closed_month_cannot_be_changed()
    {
        $a = $this->member();
        $staff = $this->recorder();
        app(CloseMonth::class)->handle(User::factory()->create(), '2026-09');

        $this->actingAs($staff)->post(route('meals.store'), $this->form('2026-09-10', [$a->id => [$this->lunch->id => 1]]))->assertSessionHasErrors();

        $this->assertSame(0, Meal::query()->count());
    }

    public function test_the_day_screen_lists_who_lived_there_that_day_with_their_counts()
    {
        $present = $this->member('2026-09-01');
        $this->member('2026-10-15');
        $this->member('2026-08-01', '2026-10-01');
        Meal::factory()->create(['member_id' => $present->id, 'meal_type_id' => $this->lunch->id, 'meal_date' => '2026-10-10', 'count' => 2]);
        $off = MealType::factory()->inactive()->create(['name' => 'Snacks']);
        $viewer = User::factory()->withPermissions(Permission::MealViewAll)->create();

        $this->actingAs($viewer)->get(route('meals.index', ['date' => '2026-10-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('meals/index')
                ->has('members', 1)
                ->where('members.0.id', $present->id)
                ->where('members.0.counts', [(string) $this->lunch->id => 2])
                ->where('types', fn ($types) => collect($types)->pluck('name')->all() === ['Lunch', 'Dinner'])
                ->where('can.record', false)
                ->where('month.meals', 2));

        $this->assertNotNull($off);
    }

    public function test_the_date_defaults_to_today_and_cannot_be_in_the_future()
    {
        $viewer = User::factory()->withPermissions(Permission::MealViewAll)->create();

        $this->actingAs($viewer)->get(route('meals.index'))->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-18'));
        $this->actingAs($viewer)->get(route('meals.index', ['date' => '2027-01-01']))->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-18'));
    }

    public function test_a_member_sees_only_their_own_meals_and_what_they_cost()
    {
        $me = $this->member();
        $other = $this->member();
        Meal::factory()->create(['member_id' => $me->id, 'meal_type_id' => $this->lunch->id, 'meal_date' => '2026-10-10', 'count' => 2]);
        Meal::factory()->create(['member_id' => $other->id, 'meal_type_id' => $this->lunch->id, 'meal_date' => '2026-10-11', 'count' => 6]);
        GroceryPurchase::factory()->create(['buyer_member_id' => $other->id, 'grand_total' => '800.00', 'purchase_date' => '2026-10-12']);

        $this->actingAs($me->user)->get(route('meals.mine'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('meals/mine')
                ->has('days', 1)
                ->where('days.0.date', '2026-10-10')
                ->where('summary.meals', 2)
                ->where('summary.total_meals', 8)
                ->where('summary.rate', '100.00')
                ->where('summary.meal_cost', '200.00'));
    }

    public function test_the_own_meals_page_needs_a_member_profile_and_the_permission()
    {
        $this->actingAs(User::factory()->create())->get(route('meals.mine'))->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(Permission::MealViewOwn)->create())->get(route('meals.mine'))->assertForbidden();
    }

    public function test_managers_and_admins_hold_the_meal_permissions_by_default()
    {
        $manager = User::factory()->create()->assignRole('Manager');
        $admin = User::factory()->create()->assignRole('Admin');
        $member = User::factory()->create()->assignRole('Member');

        foreach ([Permission::MealViewOwn, Permission::MealViewAll, Permission::MealRecord] as $permission) {
            $this->assertTrue($manager->can($permission->value));
            $this->assertTrue($admin->can($permission->value));
        }

        $this->assertTrue($member->can(Permission::MealViewOwn->value));
        $this->assertFalse($member->can(Permission::MealRecord->value));
        $this->assertFalse($member->can(Permission::MealViewAll->value));
    }
}
