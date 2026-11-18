<?php

namespace Tests\Feature\Settings;

use App\Enums\Permission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Mail\TestMail;
use App\Models\Activity;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'mailer' => 'smtp',
            'host' => 'mail.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'no-reply@example.com',
            'password' => 'super-secret-pass',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'Staya Hostel',
            ...$overrides,
        ];
    }

    private function manager(): User
    {
        return User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsManageMail)->create();
    }

    public function test_the_page_needs_the_manage_mail_permission_and_never_shows_the_password()
    {
        MailSetting::factory()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsUpdate)->create())
            ->get(route('mail-settings.edit'))
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->get(route('mail-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.host', 'mail.example.com')
                ->where('settings.has_password', true)
                ->missing('settings.password'))
            ->assertDontSee('secret-password');
    }

    public function test_admin_has_the_permission_by_default()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('mail-settings.edit'))
            ->assertOk();
    }

    public function test_saving_requires_the_permission()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsUpdate)->create())
            ->put(route('mail-settings.update'), $this->validPayload())
            ->assertForbidden();

        $this->assertSame('default', MailSetting::current()->mailer);
    }

    public function test_the_password_is_encrypted_at_rest_and_kept_out_of_the_audit_log()
    {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mail-settings.edit'));

        $settings = MailSetting::current();
        $this->assertSame('super-secret-pass', $settings->password);
        $this->assertSame($manager->id, $settings->updated_by);

        $stored = (string) DB::table('mail_settings')->value('password');
        $this->assertNotSame('super-secret-pass', $stored);
        $this->assertStringNotContainsString('super-secret-pass', $stored);

        $activity = Activity::query()->where('description', 'mail_settings_updated')->sole();
        $this->assertSame('settings', $activity->log_name);
        $this->assertSame('mail_setting', $activity->subject_type);
        $this->assertTrue($activity->causer->is($manager));
        $this->assertSame('saved', $activity->attribute_changes['attributes']['password']);
        $this->assertSame('mail.example.com', $activity->attribute_changes['attributes']['host']);
        $this->assertStringNotContainsString('super-secret-pass', json_encode($activity->attribute_changes));
    }

    public function test_a_blank_password_keeps_the_saved_one_and_a_new_one_is_logged_as_changed()
    {
        $manager = $this->manager();
        MailSetting::factory()->create();

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['password' => '', 'from_name' => 'New name']))
            ->assertSessionHasNoErrors();

        $this->assertSame('secret-password', MailSetting::current()->password);

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['password' => 'another-one', 'from_name' => 'New name']))
            ->assertSessionHasNoErrors();

        $this->assertSame('another-one', MailSetting::current()->password);

        $activity = Activity::query()->where('description', 'mail_settings_updated')->latest('id')->firstOrFail();
        $this->assertSame('changed', $activity->attribute_changes['attributes']['password']);
        $this->assertStringNotContainsString('another-one', json_encode($activity->attribute_changes));
    }

    public function test_host_and_sender_are_required_only_for_the_mailers_that_need_them()
    {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['host' => '', 'from_address' => '']))
            ->assertSessionHasErrors(['host', 'from_address']);

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['mailer' => 'default', 'host' => '', 'from_address' => '', 'password' => '']))
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['encryption' => 'plain', 'port' => 70000, 'from_address' => 'not-an-email']))
            ->assertSessionHasErrors(['encryption', 'port', 'from_address']);

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['mailer' => 'sendmail', 'host' => '', 'from_address' => '']))
            ->assertSessionHasErrors(['from_address'])
            ->assertSessionDoesntHaveErrors(['host']);

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['mailer' => 'log', 'host' => '', 'from_address' => '']))
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->put(route('mail-settings.update'), $this->validPayload(['mailer' => 'pigeon']))
            ->assertSessionHasErrors(['mailer']);
    }

    public function test_enabled_settings_replace_the_env_mail_config()
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'env@example.com']);

        MailSetting::factory()->create(['mailer' => 'default']);
        MailSetting::applyToConfig();
        $this->assertSame('log', config('mail.default'));

        MailSetting::current()->forceFill(['mailer' => 'smtp', 'port' => 587, 'encryption' => 'tls'])->save();
        MailSetting::applyToConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('secret-password', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));

        MailSetting::current()->forceFill(['encryption' => 'ssl'])->save();
        MailSetting::applyToConfig();
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_sendmail_and_log_mailers_are_applied_without_smtp_details()
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'env-host']);
        MailSetting::factory()->create(['mailer' => 'sendmail', 'host' => null, 'password' => null]);

        MailSetting::applyToConfig();
        $this->assertSame('sendmail', config('mail.default'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));
        $this->assertSame('env-host', config('mail.mailers.smtp.host'));

        MailSetting::current()->forceFill(['mailer' => 'log'])->save();
        MailSetting::applyToConfig();
        $this->assertSame('log', config('mail.default'));
    }

    public function test_incomplete_settings_do_not_override_the_env()
    {
        config(['mail.default' => 'log']);
        MailSetting::factory()->create(['host' => null]);

        MailSetting::applyToConfig();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_the_saved_password_is_sent_only_when_asked_for_and_the_view_is_logged()
    {
        MailSetting::factory()->create();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->get(route('mail-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->missing('password'));
        $this->assertDatabaseMissing('activity_log', ['description' => 'mail_password_viewed']);

        $this->actingAs($manager)
            ->get(route('mail-settings.edit'), ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'mail-settings/edit', 'X-Inertia-Partial-Data' => 'password', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->assertOk()
            ->assertJsonPath('props.password', 'secret-password');

        $activity = Activity::query()->where('description', 'mail_password_viewed')->sole();
        $this->assertTrue($activity->causer->is($manager));
        $this->assertStringNotContainsString('secret-password', json_encode($activity->attribute_changes));
    }

    public function test_saving_switches_the_running_app_over_straight_away()
    {
        config(['mail.default' => 'log']);

        $this->actingAs($this->manager())
            ->put(route('mail-settings.update'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('smtp', config('mail.default'));
    }

    private function inertiaVersion(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }

    public function test_the_test_email_goes_to_the_signed_in_user_through_the_saved_settings()
    {
        Mail::fake();
        $manager = $this->manager();
        MailSetting::factory()->create();
        config(['mail.default' => 'smtp']);

        $this->actingAs($manager)->post(route('mail-settings.test'))->assertRedirect(route('mail-settings.edit'));

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo($manager->email));
    }

    public function test_the_test_email_refuses_the_log_mailer_and_needs_the_permission()
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $this->actingAs($this->manager())->post(route('mail-settings.test'));
        Mail::assertNothingSent();

        config(['mail.default' => 'smtp']);
        $this->actingAs(User::factory()->withPermissions(Permission::SettingsView, Permission::SettingsUpdate)->create())
            ->post(route('mail-settings.test'))
            ->assertForbidden();
        Mail::assertNothingSent();
    }
}
