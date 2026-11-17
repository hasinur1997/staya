<?php

namespace App\Models;

use App\Casts\BusinessDate;
use App\Enums\CostSplit;
use App\Exceptions\MonthClosed;
use Carbon\CarbonImmutable;
use Database\Factories\MonthClosureFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A reporting month that has been closed. While closed (no reopened_at),
 * nothing dated in it can be created or changed, whatever the permissions.
 *
 * @property int $id
 * @property CarbonImmutable $month
 * @property int $closed_by
 * @property CarbonImmutable $closed_at
 * @property int|null $reopened_by
 * @property CarbonImmutable|null $reopened_at
 * @property string|null $reopen_reason
 * @property CostSplit|null $cost_split
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['month', 'closed_by', 'closed_at', 'reopened_by', 'reopened_at', 'reopen_reason', 'cost_split'])]
class MonthClosure extends Model
{
    /** @use HasFactory<MonthClosureFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => BusinessDate::class,
            'closed_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'cost_split' => CostSplit::class,
        ];
    }

    /**
     * Whether the month containing the given business date is closed.
     */
    public static function isClosed(string|DateTimeInterface $date): bool
    {
        return static::query()->closed()->where('month', self::monthOf($date)->toDateString())->exists();
    }

    /**
     * Refuse to continue when the given business date is in a closed month.
     *
     * @throws MonthClosed
     */
    public static function assertOpen(string|DateTimeInterface $date, string $field = 'entry_date'): void
    {
        if (static::isClosed($date)) {
            throw MonthClosed::for(self::monthOf($date), $field);
        }
    }

    /**
     * Refuse to continue when the month of the given business date, or any
     * later month, is closed. Used for changes that reach forward in time,
     * such as stay dates, which decide who shares each later month.
     *
     * @throws MonthClosed
     */
    public static function assertOpenFrom(string|DateTimeInterface $date, string $field): void
    {
        $closed = static::query()->closed()
            ->where('month', '>=', self::monthOf($date)->toDateString())
            ->orderBy('month')
            ->first();

        if ($closed !== null) {
            throw MonthClosed::for($closed->month, $field);
        }
    }

    /**
     * The first day of the month a business date falls in.
     */
    public static function monthOf(string|DateTimeInterface $date): CarbonImmutable
    {
        $day = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr($date, 0, 10);

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $day);

        if (! $parsed) {
            throw new InvalidArgumentException("[{$day}] is not a Y-m-d date.");
        }

        return $parsed->startOfMonth();
    }

    /**
     * Months that are closed right now.
     *
     * @param  Builder<MonthClosure>  $query
     */
    #[Scope]
    protected function closed(Builder $query): void
    {
        $query->whereNull('reopened_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
