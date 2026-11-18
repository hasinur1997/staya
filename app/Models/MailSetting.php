<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MailSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single row of email delivery settings. Unless its mailer is
 * `default`, it overrides the MAIL_* values from .env (see `applyToConfig()`). The password is
 * encrypted at rest and never leaves the server: controllers send only
 * whether one is saved.
 *
 * @property int $id
 * @property string $mailer
 * @property string|null $host
 * @property int $port
 * @property string $encryption
 * @property string|null $username
 * @property string|null $password
 * @property string|null $from_address
 * @property string|null $from_name
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['mailer', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'])]
#[Hidden(['password'])]
class MailSetting extends Model
{
    /** @use HasFactory<MailSettingFactory> */
    use HasFactory;

    /** Leave the server's .env in charge. */
    public const string USE_ENV = 'default';

    public const string SMTP = 'smtp';

    public const string SENDMAIL = 'sendmail';

    /** Deliver nothing: emails are only written to the log. */
    public const string LOG = 'log';

    /** Implicit TLS, usually port 465. */
    public const string SSL = 'ssl';

    /** STARTTLS, usually port 587. */
    public const string TLS = 'tls';

    /**
     * The settings row, created switched off if it is missing.
     */
    public static function current(): self
    {
        return static::query()->find(1)
            ?? tap(static::query()->forceCreate(['id' => 1]))->refresh();
    }

    /**
     * The mailers an Admin can choose.
     *
     * @return list<string>
     */
    public static function mailers(): array
    {
        return [self::USE_ENV, self::SMTP, self::SENDMAIL, self::LOG];
    }

    /**
     * Point Laravel's mail config at these settings unless they leave it to
     * .env or are incomplete. Tolerates the table not being migrated yet.
     */
    public static function applyToConfig(): void
    {
        $settings = rescue(fn (): ?self => static::query()->find(1), null, false);

        if (! $settings?->isUsable()) {
            return;
        }

        config(['mail.default' => $settings->mailer]);

        if (filled($settings->from_address)) {
            config([
                'mail.from.address' => $settings->from_address,
                'mail.from.name' => $settings->from_name ?: config('mail.from.name'),
            ]);
        }

        if ($settings->mailer === self::SMTP) {
            config([
                'mail.mailers.smtp.host' => $settings->host,
                'mail.mailers.smtp.port' => $settings->port,
                'mail.mailers.smtp.scheme' => $settings->encryption === self::SSL ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => $settings->username,
                'mail.mailers.smtp.password' => $settings->password,
            ]);
        }
    }

    /**
     * Whether these settings take over from .env and have what their mailer needs.
     */
    public function isUsable(): bool
    {
        return match ($this->mailer) {
            self::SMTP => filled($this->host) && filled($this->from_address),
            self::SENDMAIL => filled($this->from_address),
            self::LOG => true,
            default => false,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The fields recorded as before/after values in the audit log. The
     * password is never written to it, only whether one is saved.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'mailer' => $this->mailer,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'password' => filled($this->password) ? 'saved' : 'not set',
        ];
    }
}
