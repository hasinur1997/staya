<?php

namespace Tests\Feature\Settings;

use App\Concerns\UploadValidationRules;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MessSettingsTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'mess_name' => 'Green House Mess',
            'mess_address' => 'House 12, Road 4, Dhanmondi',
            'opening_balance' => '1500.50',
            'fund_start_date' => '2026-10-01',
            'grocery_edit_window_minutes' => 45,
            'members_can_view_balance' => false,
            'max_upload_kb' => 2048,
            ...$overrides,
        ];
    }

    public function test_the_settings_row_is_created_with_its_defaults()
    {
        $settings = MessSetting::current();

        $this->assertSame(1, $settings->id);
        $this->assertSame('BDT', $settings->currency);
        $this->assertSame('Asia/Dhaka', $settings->timezone);
        $this->assertSame('0.00', $settings->opening_balance);
        $this->assertSame(30, $settings->grocery_edit_window_minutes);
        $this->assertTrue($settings->members_can_view_balance);
        $this->assertSame(5120, $settings->max_upload_kb);
        $this->assertSame(1, MessSetting::query()->count());
    }

    public function test_the_settings_page_requires_settings_view()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('mess-settings.edit'))
            ->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView)->create())
            ->get(route('mess-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('mess-settings/edit')
                ->where('settings.opening_balance', '0.00')
                ->where('settings.currency', 'BDT')
                ->where('can.update', false));
    }

    public function test_saving_requires_settings_update()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView)->create())
            ->put(route('mess-settings.update'), $this->validPayload())
            ->assertForbidden();

        $this->assertNull(MessSetting::current()->mess_name);
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_admin_can_save_the_settings_and_the_change_is_logged()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('mess-settings.update'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mess-settings.edit'));

        $settings = MessSetting::current();
        $this->assertSame('Green House Mess', $settings->mess_name);
        $this->assertSame('1500.50', $settings->opening_balance);
        $this->assertSame('2026-10-01', $settings->fund_start_date?->toDateString());
        $this->assertSame(45, $settings->grocery_edit_window_minutes);
        $this->assertFalse($settings->members_can_view_balance);
        $this->assertSame(2048, $settings->max_upload_kb);
        $this->assertSame($admin->id, $settings->updated_by);

        $activity = Activity::query()->where('description', 'settings_updated')->sole();
        $this->assertSame('settings', $activity->log_name);
        $this->assertSame('mess_setting', $activity->subject_type);
        $this->assertTrue($activity->causer->is($admin));
        $this->assertSame('127.0.0.1', $activity->ip_address);
        $this->assertSame('0.00', $activity->attribute_changes['old']['opening_balance']);
        $this->assertSame('1500.50', $activity->attribute_changes['attributes']['opening_balance']);
        $this->assertSame(30, $activity->attribute_changes['old']['grocery_edit_window_minutes']);
        $this->assertSame(45, $activity->attribute_changes['attributes']['grocery_edit_window_minutes']);
        $this->assertTrue($activity->attribute_changes['old']['members_can_view_balance']);
        $this->assertFalse($activity->attribute_changes['attributes']['members_can_view_balance']);
    }

    public function test_only_changed_fields_are_logged_and_an_unchanged_save_logs_nothing()
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->validPayload();

        $this->actingAs($admin)->put(route('mess-settings.update'), $payload);
        $this->actingAs($admin)->put(route('mess-settings.update'), $payload);

        $this->assertSame(1, Activity::query()->where('description', 'settings_updated')->count());

        $this->actingAs($admin)->put(route('mess-settings.update'), [...$payload, 'max_upload_kb' => 1024]);

        $changes = Activity::query()->where('description', 'settings_updated')->latest('id')->first()->attribute_changes;
        $this->assertSame(['max_upload_kb' => 2048], $changes['old']);
        $this->assertSame(['max_upload_kb' => 1024], $changes['attributes']);
    }

    public function test_the_opening_balance_is_stored_at_two_decimal_places()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('mess-settings.update'), $this->validPayload(['opening_balance' => '1200']))
            ->assertSessionHasNoErrors();

        $this->assertSame('1200.00', MessSetting::current()->opening_balance);
    }

    public function test_invalid_values_are_rejected()
    {
        $admin = User::factory()->admin()->create();

        foreach (['-5.00', '12.345', 'abc', '1e3', '12345678901.00'] as $amount) {
            $this->actingAs($admin)
                ->put(route('mess-settings.update'), $this->validPayload(['opening_balance' => $amount]))
                ->assertSessionHasErrors('opening_balance');
        }

        $this->actingAs($admin)
            ->put(route('mess-settings.update'), $this->validPayload([
                'grocery_edit_window_minutes' => 0,
                'max_upload_kb' => 50,
            ]))
            ->assertSessionHasErrors(['grocery_edit_window_minutes', 'max_upload_kb']);

        $this->assertSame('0.00', MessSetting::current()->opening_balance);
    }

    public function test_the_fund_start_date_cannot_be_in_the_future_in_dhaka()
    {
        // 23:30 on 18 Oct in Dhaka.
        $this->travelTo('2026-10-18 17:30:00');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('mess-settings.update'), $this->validPayload(['fund_start_date' => '2026-10-19']))
            ->assertSessionHasErrors(['fund_start_date' => 'The date cannot be in the future.']);

        // 00:30 on 19 Oct in Dhaka, still 18 Oct in UTC.
        $this->travelTo('2026-10-18 18:30:00');

        $this->actingAs($admin)
            ->put(route('mess-settings.update'), $this->validPayload(['fund_start_date' => '2026-10-19']))
            ->assertSessionHasNoErrors();
    }

    public function test_setting_changes_take_effect_on_the_next_request()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('name', config('app.name'))
                ->where('mess.editWindowMinutes', 30)
                ->where('mess.maxUploadKb', 5120));

        $this->actingAs($admin)
            ->put(route('mess-settings.update'), $this->validPayload([
                'grocery_edit_window_minutes' => 10,
                'max_upload_kb' => 1024,
                'members_can_view_balance' => false,
            ]));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('name', 'Green House Mess')
                ->where('mess.editWindowMinutes', 10)
                ->where('mess.maxUploadKb', 1024));

        $settings = MessSetting::current();
        $createdAt = now()->subMinutes(15);
        $this->assertTrue($settings->editWindowClosesAt($createdAt)->isPast());
        $this->assertFalse($settings->members_can_view_balance);

        $rules = new class
        {
            use UploadValidationRules;

            /** @return array<int, mixed> */
            public function rules(): array
            {
                return $this->uploadRules(['pdf']);
            }
        };

        $file = UploadedFile::fake()->create('nid.pdf', 2000, 'application/pdf');
        $this->assertTrue(Validator::make(['file' => $file], ['file' => $rules->rules()])->fails());

        $settings->update(['max_upload_kb' => 4096]);
        $this->assertTrue(Validator::make(['file' => $file], ['file' => $rules->rules()])->passes());
    }
}
