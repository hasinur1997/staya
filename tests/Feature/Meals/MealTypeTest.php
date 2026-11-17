<?php

namespace Tests\Feature\Meals;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MealTypeTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private function admin(): User
    {
        return User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsUpdate)->create();
    }

    public function test_breakfast_lunch_and_dinner_exist_from_the_start_and_snacks_is_switched_off()
    {
        $this->assertSame(['Breakfast', 'Lunch', 'Dinner', 'Snacks'], MealType::query()->ordered()->pluck('name')->all());
        $this->assertSame(['Breakfast', 'Lunch', 'Dinner'], MealType::query()->active()->ordered()->pluck('name')->all());
    }

    public function test_the_list_needs_settings_view_and_changes_need_settings_update()
    {
        $type = MealType::query()->first();
        $viewer = User::factory()->withPermissions(Permission::SettingsView)->create();
        $nobody = User::factory()->create();

        $this->actingAs($nobody)->get(route('meal-types.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('meal-types.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('meals/types')->where('can.manage', false)->has('types', 4));

        $this->actingAs($viewer)->post(route('meal-types.store'), ['name' => 'Brunch', 'sort_order' => 5, 'is_active' => true])->assertForbidden();
        $this->actingAs($viewer)->put(route('meal-types.update', $type), ['name' => 'Changed', 'sort_order' => 1, 'is_active' => true])->assertForbidden();

        $this->assertSame(4, MealType::query()->count());
        $this->assertNotSame('Changed', $type->fresh()->name);
    }

    public function test_a_meal_type_can_be_added_and_is_logged()
    {
        $this->actingAs($this->admin())->post(route('meal-types.store'), ['name' => 'Brunch', 'sort_order' => 5, 'is_active' => true])->assertSessionHasNoErrors();

        $type = MealType::query()->where('name', 'Brunch')->sole();
        $this->assertTrue($type->is_active);
        $this->assertSame('Brunch', Activity::query()->where('description', 'meal_type_created')->sole()->attribute_changes['attributes']['name']);
    }

    public function test_a_meal_type_can_be_renamed_and_switched_off_with_the_change_logged()
    {
        $type = MealType::query()->where('name', 'Lunch')->sole();

        $this->actingAs($this->admin())->put(route('meal-types.update', $type), ['name' => 'Midday meal', 'sort_order' => 2, 'is_active' => false])->assertSessionHasNoErrors();

        $type->refresh();
        $this->assertSame('Midday meal', $type->name);
        $this->assertFalse($type->is_active);
        $changes = Activity::query()->where('description', 'meal_type_updated')->sole()->attribute_changes;
        $this->assertSame('Lunch', $changes['old']['name']);
        $this->assertTrue($changes['old']['is_active']);
        $this->assertFalse($changes['attributes']['is_active']);
    }

    public function test_names_must_be_unique_and_filled()
    {
        $admin = $this->admin();
        $type = MealType::query()->where('name', 'Dinner')->sole();

        $this->actingAs($admin)->post(route('meal-types.store'), ['name' => 'Lunch', 'sort_order' => 5, 'is_active' => true])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('meal-types.store'), ['name' => '', 'sort_order' => 5, 'is_active' => true])->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('meal-types.update', $type), ['name' => 'Dinner', 'sort_order' => 3, 'is_active' => true])->assertSessionHasNoErrors();
    }

    public function test_a_type_with_meals_is_kept_when_switched_off_and_has_no_delete_route()
    {
        $type = MealType::query()->where('name', 'Lunch')->sole();
        Meal::factory()->create(['meal_type_id' => $type->id]);

        $this->actingAs($this->admin())->delete('/meals/types/'.$type->id)->assertStatus(405);

        $this->assertSame(1, Meal::query()->count());
        $this->assertNotNull($type->fresh());
    }
}
