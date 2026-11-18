<?php

namespace Tests\Feature\Mail;

use App\Mail\MemberInvitationMail;
use App\Mail\TestMail;
use App\Models\MemberInvitation;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BrandedMailTest extends TestCase
{
    use RefreshDatabase;

    private function setDefaultLanguage(string $locale): void
    {
        MessSetting::current()->forceFill(['default_locale' => $locale])->save();
    }

    public function test_the_invitation_email_is_branded_and_in_english_by_default()
    {
        $invitation = MemberInvitation::factory()->create();

        $html = (new MemberInvitationMail($invitation, 'https://staya.test/invitations/abc', 'Karim'))->render();

        $this->assertStringContainsString('You are invited to join', $html);
        $this->assertStringContainsString('Karim invited you to join the mess.', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('https://staya.test/invitations/abc', $html);
        $this->assertStringContainsString('automated message', $html);
    }

    public function test_the_invitation_email_uses_the_default_language_set_in_settings()
    {
        $this->setDefaultLanguage('bn');
        $invitation = MemberInvitation::factory()->create();

        $html = (new MemberInvitationMail($invitation, 'https://staya.test/invitations/abc', 'Karim'))->render();

        $this->assertStringContainsString('আপনাকে আমন্ত্রণ জানানো হয়েছে', $html);
        $this->assertStringContainsString('আপনার তথ্য পূরণ করুন', $html);
        $this->assertStringNotContainsString('Fill in your details', $html);
    }

    public function test_the_password_reset_email_is_branded_and_follows_the_default_language()
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $html = (string) $notification->toMail($user)->render();

            return str_contains($html, 'Reset your password')
                && str_contains($html, 'This link expires in 60 minutes.')
                && str_contains($html, '/reset-password/')
                && str_contains($html, 'apple-touch-icon.png');
        });

        $this->setDefaultLanguage('bn');
        $this->assertSame('bn', $user->preferredLocale());

        $notification = new ResetPassword('token');
        $html = (string) $notification->toMail($user)->render();
        $this->assertStringContainsString('Reset your password', $html);

        app()->setLocale('bn');
        $this->assertStringContainsString('আপনার পাসওয়ার্ড রিসেট করুন', (string) $notification->toMail($user)->render());
    }

    public function test_the_verification_email_is_branded()
    {
        $user = User::factory()->unverified()->create();

        $html = (string) (new VerifyEmail)->toMail($user)->render();

        $this->assertStringContainsString('Verify your email address', $html);
        $this->assertStringContainsString('/email/verify/', $html);
    }

    public function test_the_test_mail_command_sends_through_the_configured_mailer()
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);

        $this->artisan('staya:test-mail', ['email' => 'owner@example.com'])->assertSuccessful();

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('owner@example.com'));
    }

    public function test_the_test_mail_command_refuses_the_log_mailer()
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $this->artisan('staya:test-mail', ['email' => 'owner@example.com'])->assertFailed();

        Mail::assertNothingSent();
    }
}
