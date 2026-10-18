<?php

namespace App\Listeners;

use App\Enums\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Laravel\Fortify\Fortify;

/**
 * Writes logins, failed attempts, lockouts and logouts to the audit log.
 * Passwords are never recorded.
 */
class RecordAuthActivity
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // Quietly: this is not an edit of the account.
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity()
            ->useLog(AuditLog::Auth)
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('login')
            ->withProperties(['remember' => $event->remember])
            ->log('login');
    }

    public function handleFailed(Failed $event): void
    {
        activity()
            ->useLog(AuditLog::Auth)
            ->byAnonymous()
            ->event('login_failed')
            ->withProperties(['email_tried' => $this->emailTried($event->credentials[Fortify::username()] ?? null)])
            ->log('login_failed');
    }

    public function handleLockout(Lockout $event): void
    {
        activity()
            ->useLog(AuditLog::Auth)
            ->byAnonymous()
            ->event('lockout')
            ->withProperties(['email_tried' => $this->emailTried($event->request->input(Fortify::username()))])
            ->log('lockout');
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        activity()
            ->useLog(AuditLog::Auth)
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('logout')
            ->log('logout');
    }

    /**
     * Whatever was typed, trimmed to the column-friendly length of an email.
     */
    private function emailTried(mixed $value): ?string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 255) : null;
    }
}
