<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('staya:test-mail {email : The address to send the test email to}')]
#[Description('Send a test email to check that the mail settings deliver real email')]
class SendTestMail extends Command
{
    /**
     * Send one email now (not queued) and report the mail settings in use, so
     * a wrong host, port or password shows up here instead of on a member.
     */
    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        $this->components->twoColumnDetail('Mailer', $mailer);
        $this->components->twoColumnDetail('From', $from);

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->components->warn("MAIL_MAILER is '{$mailer}': nothing is delivered. Set MAIL_MAILER=smtp and the MAIL_* values.");

            return self::FAILURE;
        }

        $email = $this->argument('email');

        if (! is_string($email)) {
            $this->components->error('Give one email address.');

            return self::FAILURE;
        }

        try {
            Mail::to($email)->send(new TestMail);
        } catch (Throwable $exception) {
            $this->components->error('Sending failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Test email sent to {$email}. Check the inbox and the spam folder.");

        return self::SUCCESS;
    }
}
