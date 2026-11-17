<?php

namespace App\Models;

use App\Casts\BusinessDate;
use App\Enums\DepositMethod;
use App\Enums\DepositStatus;
use App\Enums\LedgerEntryType;
use App\Exceptions\ImmutableRecord;
use Carbon\CarbonImmutable;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A member's cash deposit. Only an approved deposit moves money, through
 * its `deposit` ledger entry, which is posted exactly once. Amount, date,
 * member and method are fixed once saved; a mistake is reversed and
 * submitted again. Deposits are never deleted.
 *
 * @property int $id
 * @property int $member_id
 * @property string $amount
 * @property CarbonImmutable $deposit_date
 * @property DepositMethod $method
 * @property DepositStatus $status
 * @property int $submitted_by
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $rejection_reason
 * @property bool $is_self_approved
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $reversed_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['member_id', 'amount', 'deposit_date', 'method', 'submitted_by'])]
class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    /**
     * Fields that never change after insert.
     *
     * @var list<string>
     */
    public const array FIXED = ['member_id', 'amount', 'deposit_date', 'method', 'submitted_by'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'method' => 'cash',
        'status' => 'pending',
        'is_self_approved' => false,
    ];

    protected static function booted(): void
    {
        static::updating(function (Deposit $deposit): void {
            $changed = array_values(array_filter(self::FIXED, fn (string $field) => $deposit->isDirty($field)));

            if ($changed !== []) {
                throw ImmutableRecord::fields($deposit->getTable(), $changed);
            }
        });

        static::deleting(fn (Deposit $deposit) => throw ImmutableRecord::deletion($deposit->getTable()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deposit_date' => BusinessDate::class,
            'method' => DepositMethod::class,
            'status' => DepositStatus::class,
            'is_self_approved' => 'boolean',
            'reviewed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Whether the deposit belongs to the user's own member profile.
     */
    public function belongsToUser(User $user): bool
    {
        return $this->member->user_id === $user->id;
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Every ledger entry this deposit caused: its credit and any reversal.
     *
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * The credit posted on approval.
     *
     * @return MorphOne<LedgerEntry, $this>
     */
    public function creditEntry(): MorphOne
    {
        return $this->morphOne(LedgerEntry::class, 'source')->where('type', LedgerEntryType::Deposit);
    }

    /**
     * The fields recorded as before/after values in the audit log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'member_id' => $this->member_id,
            'amount' => $this->amount,
            'deposit_date' => $this->deposit_date->toDateString(),
            'method' => $this->method->value,
            'status' => $this->status->value,
            'reviewed_by' => $this->reviewed_by,
            'rejection_reason' => $this->rejection_reason,
            'is_self_approved' => $this->is_self_approved,
            'reversed_by' => $this->reversed_by,
            'reversal_reason' => $this->reversal_reason,
        ];
    }
}
