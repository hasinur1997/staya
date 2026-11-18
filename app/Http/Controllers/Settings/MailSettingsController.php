<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateMailSettings;
use App\Enums\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMailSettingsRequest;
use App\Mail\TestMail;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingsController extends Controller
{
    /**
     * Show the email settings form. The saved password is never sent, only
     * whether there is one.
     */
    public function edit(Request $request): Response
    {
        $settings = MailSetting::current();

        Gate::authorize('manage', $settings);

        return Inertia::render('mail-settings/edit', [
            'settings' => [
                'mailer' => $settings->mailer,
                'host' => $settings->host,
                'port' => $settings->port,
                'encryption' => $settings->encryption,
                'username' => $settings->username,
                'has_password' => filled($settings->password),
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
                'updated_at' => $settings->updated_at?->toIso8601String(),
                'updated_by' => $settings->updatedBy?->name,
            ],
            'mailers' => [
                ['value' => MailSetting::USE_ENV, 'label' => __('Server default (.env)')],
                ['value' => MailSetting::SMTP, 'label' => __('SMTP')],
                ['value' => MailSetting::SENDMAIL, 'label' => __('Sendmail (the server\'s own mail program)')],
                ['value' => MailSetting::LOG, 'label' => __('Log only (nothing is delivered)')],
            ],
            // Sent only when the person presses "show" on the password field.
            'password' => Inertia::optional(function () use ($settings): ?string {
                activity()
                    ->useLog(AuditLog::Settings)
                    ->performedOn($settings)
                    ->event('viewed')
                    ->log('mail_password_viewed');

                return $settings->password;
            }),
            'encryptions' => [
                ['value' => MailSetting::SSL, 'label' => __('SSL / TLS (usually port 465)')],
                ['value' => MailSetting::TLS, 'label' => __('STARTTLS (usually port 587)')],
            ],
            // What the app is using right now, wherever it came from.
            'delivering' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'testRecipient' => $request->user()->email,
        ]);
    }

    /**
     * Save the email settings.
     */
    public function update(UpdateMailSettingsRequest $request, UpdateMailSettings $updateMailSettings): RedirectResponse
    {
        $updateMailSettings->handle($request->user(), $request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email settings saved.')]);

        return to_route('mail-settings.edit');
    }

    /**
     * Send a real test email to the signed-in user, with the saved settings.
     */
    public function test(Request $request): RedirectResponse
    {
        Gate::authorize('manage', MailSetting::current());

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Sending is switched off, so no email can be delivered. Turn it on and save first.')]);

            return to_route('mail-settings.edit');
        }

        try {
            Mail::to($request->user()->email)->send(new TestMail);
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The test email could not be sent: :error', ['error' => $exception->getMessage()])]);

            return to_route('mail-settings.edit');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent to :email. Check the inbox and the spam folder.', ['email' => $request->user()->email])]);

        return to_route('mail-settings.edit');
    }
}
