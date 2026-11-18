<?php

namespace App\Models;

use App\Enums\CostSplit;
use Carbon\CarbonImmutable;
use Database\Factories\MessSettingFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single row of mess-wide settings. Read it through `current()` so a
 * change takes effect on the next request, without a deploy.
 *
 * @property int $id
 * @property string|null $mess_name
 * @property string|null $mess_address
 * @property string $currency
 * @property string $timezone
 * @property string $opening_balance
 * @property CarbonImmutable|null $fund_start_date
 * @property int $grocery_edit_window_minutes
 * @property bool $members_can_view_balance
 * @property int $max_upload_kb
 * @property CostSplit $cost_split
 * @property string $default_locale
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'mess_name',
    'mess_address',
    'opening_balance',
    'fund_start_date',
    'grocery_edit_window_minutes',
    'members_can_view_balance',
    'max_upload_kb',
    'cost_split',
    'default_locale',
])]
class MessSetting extends Model
{
    private const INSTANCE_KEY = 'mess-settings.current';

    /** @use HasFactory<MessSettingFactory> */
    use HasFactory;

    /**
     * The settings row, created with the defaults if it is missing.
     */
    public static function current(): self
    {
        $app = app();

        if ($app->bound(self::INSTANCE_KEY)) {
            return $app->make(self::INSTANCE_KEY);
        }

        $settings = static::query()->find(1)
            ?? tap(static::query()->forceCreate(['id' => 1]))->refresh();

        $app->instance(self::INSTANCE_KEY, $settings);

        return $settings;
    }

    /**
     * Keep the settings row for the rest of the request, so the many places
     * that read it share one query. Saving or deleting it forgets the copy.
     */
    protected static function booted(): void
    {
        $forget = fn () => app()->forgetInstance(self::INSTANCE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * The language for anyone who has not chosen one, and for emails: the
     * Admin's choice if it is still supported, else the configured locale.
     * Tolerates the settings table not being migrated yet.
     */
    public static function defaultLocale(): string
    {
        $fallback = (string) config('app.locale');
        $locale = rescue(fn (): string => static::current()->default_locale, $fallback, false);

        return array_key_exists($locale, config('app.supported_locales', [])) ? $locale : $fallback;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'fund_start_date' => 'immutable_date',
            'grocery_edit_window_minutes' => 'integer',
            'members_can_view_balance' => 'boolean',
            'max_upload_kb' => 'integer',
            'cost_split' => CostSplit::class,
        ];
    }

    /**
     * When the owner's edit window closes for a record created at the given
     * (server) time.
     */
    public function editWindowClosesAt(DateTimeInterface $createdAt): CarbonImmutable
    {
        return CarbonImmutable::instance($createdAt)->addMinutes($this->grocery_edit_window_minutes);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The fields recorded as before/after values in the audit log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'mess_name' => $this->mess_name,
            'mess_address' => $this->mess_address,
            'opening_balance' => $this->opening_balance,
            'fund_start_date' => $this->fund_start_date?->toDateString(),
            'grocery_edit_window_minutes' => $this->grocery_edit_window_minutes,
            'members_can_view_balance' => $this->members_can_view_balance,
            'max_upload_kb' => $this->max_upload_kb,
            'cost_split' => $this->cost_split->value,
            'default_locale' => $this->default_locale,
        ];
    }
}
