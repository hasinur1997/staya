<?php

namespace App\Models;

use App\Casts\BusinessDate;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Exceptions\AppendOnlyViolation;
use Carbon\CarbonImmutable;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One movement of the shared fund. Append-only: corrections are reversal
 * entries or adjustments, never edits. Post through PostLedgerEntries so
 * the cached fund balance stays equal to the ledger sum.
 *
 * @property int $id
 * @property CarbonImmutable $entry_date
 * @property LedgerEntryType $type
 * @property LedgerDirection $direction
 * @property string $amount
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reverses_entry_id
 * @property string|null $reason
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'entry_date',
    'type',
    'direction',
    'amount',
    'source_type',
    'source_id',
    'reverses_entry_id',
    'reason',
    'created_by',
])]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (LedgerEntry $entry) => throw AppendOnlyViolation::for($entry->getTable()));
        static::deleting(fn (LedgerEntry $entry) => throw AppendOnlyViolation::for($entry->getTable()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => BusinessDate::class,
            'type' => LedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'amount' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Whether the ledger holds anything other than opening-balance entries
     * and their reversals. Once it does, the opening balance and fund start
     * date are locked and corrections go through manual adjustments.
     */
    public static function hasActivityBeyondOpeningBalance(): bool
    {
        return static::query()
            ->where(fn (Builder $query) => $query
                ->whereNotIn('type', [LedgerEntryType::OpeningBalance, LedgerEntryType::Reversal])
                ->orWhere(fn (Builder $query) => $query
                    ->where('type', LedgerEntryType::Reversal)
                    ->whereHas('reverses', fn (Builder $query) => $query->where('type', '!=', LedgerEntryType::OpeningBalance))))
            ->exists();
    }

    /**
     * The amount with its sign: positive for a credit, negative for a debit.
     *
     * @return numeric-string
     */
    public function signedAmount(): string
    {
        /** @var numeric-string $amount */
        $amount = $this->amount;

        return $this->direction === LedgerDirection::Credit ? $amount : bcsub('0', $amount, 2);
    }

    /**
     * The record that caused this entry (a deposit, purchase or payable).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entry this one reverses.
     *
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reverses_entry_id');
    }

    /**
     * The entry that reversed this one, if any.
     *
     * @return HasOne<LedgerEntry, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(LedgerEntry::class, 'reverses_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The fields recorded in the audit log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date->toDateString(),
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'reverses_entry_id' => $this->reverses_entry_id,
            'reason' => $this->reason,
        ];
    }
}
