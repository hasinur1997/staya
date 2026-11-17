<?php

namespace Tests\Feature\Settings;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use App\Models\LedgerEntry;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ResetMessDataTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    /**
     * @return array<string, string>
     */
    private function confirmed(array $overrides = []): array
    {
        return ['confirmation' => 'RESET', 'password' => 'password', 'reason' => 'Starting the new year.', ...$overrides];
    }

    private function resetter(): User
    {
        return User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsResetData)->create();
    }

    /**
     * A mess with deposits, purchases, a payable, a month closure and meals.
     */
    private function messWithHistory(): Member
    {
        $member = Member::factory()->active()->create();
        $other = Member::factory()->active()->create();

        Deposit::factory()->approved()->for($member)->create();
        Deposit::factory()->for($other)->create();
        $purchase = GroceryPurchase::factory()->settled()->create(['receipt_path' => 'grocery/receipts/receipt.jpg']);
        Storage::disk('uploads')->put('grocery/receipts/receipt.jpg', 'receipt');
        MonthClosure::factory()->create();
        Meal::factory()->create(['member_id' => $member->id]);

        $settings = MessSetting::current();
        $settings->forceFill(['opening_balance' => '500.00', 'fund_start_date' => '2026-10-01', 'mess_name' => 'Green House'])->save();

        $this->assertGreaterThan(0, LedgerEntry::query()->count());
        $this->assertNotNull($purchase);

        return $member;
    }

    public function test_the_reset_erases_every_financial_and_meal_record_and_zeroes_the_fund()
    {
        Storage::fake('uploads');
        $this->messWithHistory();
        $actor = $this->resetter();
        $mealTypes = MealType::query()->count();
        $members = Member::query()->count();
        $users = User::query()->count();

        $this->actingAs($actor)
            ->post(route('mess-settings.reset'), $this->confirmed())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mess-settings.edit'));

        foreach ([Deposit::class, GroceryPurchase::class, GroceryPurchaseItem::class, BuyerPayable::class, LedgerEntry::class, MonthClosure::class, Meal::class] as $model) {
            $this->assertSame(0, $model::query()->count(), $model);
        }

        $this->assertSame('0.00', Fund::current()->balance);
        $this->assertSame('0.00', MessSetting::current()->opening_balance);
        $this->assertNull(MessSetting::current()->fund_start_date);
        Storage::disk('uploads')->assertMissing('grocery/receipts/receipt.jpg');

        $this->assertSame($mealTypes, MealType::query()->count());
        $this->assertSame($members, Member::query()->count());
        $this->assertSame($users, User::query()->count());
        $this->assertSame('Green House', MessSetting::current()->mess_name);
    }

    public function test_the_reset_is_recorded_in_the_audit_log_with_who_why_and_how_much()
    {
        Storage::fake('uploads');
        $this->messWithHistory();
        $actor = $this->resetter();

        $this->actingAs($actor)->post(route('mess-settings.reset'), $this->confirmed())->assertSessionHasNoErrors();

        $activity = Activity::query()->where('description', 'data_reset')->sole();

        $this->assertTrue($activity->causer->is($actor));
        $this->assertSame('Starting the new year.', $activity->properties['reason']);
        $this->assertSame(2, $activity->properties['erased']['deposits']);
        $this->assertNotNull($activity->properties['fund_balance_before']);
        $this->assertGreaterThan(0, Activity::query()->count());
    }

    public function test_the_fund_can_be_used_again_after_a_reset()
    {
        Storage::fake('uploads');
        $member = $this->messWithHistory();

        $this->actingAs($this->resetter())->post(route('mess-settings.reset'), $this->confirmed())->assertSessionHasNoErrors();

        Deposit::factory()->approved()->for($member)->create(['amount' => '1000.00']);

        $this->assertSame('1000.00', Fund::current()->balance);
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_resetting_needs_the_reset_permission()
    {
        $this->messWithHistory();

        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsUpdate)->create())
            ->post(route('mess-settings.reset'), $this->confirmed())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('mess-settings.reset'), $this->confirmed())
            ->assertForbidden();

        $this->assertGreaterThan(0, Deposit::query()->count());
        $this->assertGreaterThan(0, LedgerEntry::query()->count());
    }

    public function test_guests_are_sent_to_log_in()
    {
        $this->post(route('mess-settings.reset'), $this->confirmed())->assertRedirect(route('login'));
    }

    public function test_the_phrase_password_and_reason_are_all_required_and_nothing_is_erased_without_them()
    {
        $this->messWithHistory();
        $actor = $this->resetter();

        $this->actingAs($actor)->post(route('mess-settings.reset'), $this->confirmed(['confirmation' => 'reset']))->assertSessionHasErrors('confirmation');
        $this->actingAs($actor)->post(route('mess-settings.reset'), $this->confirmed(['password' => 'wrong-password']))->assertSessionHasErrors('password');
        $this->actingAs($actor)->post(route('mess-settings.reset'), $this->confirmed(['reason' => '']))->assertSessionHasErrors('reason');

        $this->assertGreaterThan(0, Deposit::query()->count());
        $this->assertGreaterThan(0, LedgerEntry::query()->count());
        $this->assertGreaterThan(0, Meal::query()->count());
        $this->assertNotSame('0.00', Fund::current()->balance);
        $this->assertSame(0, Activity::query()->where('description', 'data_reset')->count());
    }

    public function test_only_people_who_may_reset_see_the_danger_zone_flag()
    {
        $this->actingAs($this->resetter())->get(route('mess-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('can.resetData', true));

        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView)->create())->get(route('mess-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('can.resetData', false));
    }
}
