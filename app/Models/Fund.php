<?php

namespace App\Models;

use App\Exceptions\LedgerViolation;
use Carbon\CarbonImmutable;
use Database\Factories\FundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single shared mess fund (row id 1). `balance` is a cache of the
 * ledger sum, written only by PostLedgerEntries. Nobody types a balance in.
 *
 * @property int $id
 * @property string $balance
 * @property CarbonImmutable|null $updated_at
 */
class Fund extends Model
{
    /** @use HasFactory<FundFactory> */
    use HasFactory;

    public const null CREATED_AT = null;

    protected $table = 'fund';

    /**
     * The fund row, created at ৳0 if it is missing. Read-only use: to post,
     * go through PostLedgerEntries, which locks it.
     */
    public static function current(): self
    {
        return static::query()->find(1)
            ?? tap(static::query()->forceCreate(['id' => 1]))->refresh();
    }

    /**
     * The fund row, locked for update until the surrounding transaction
     * ends. Every balance check must read it through here.
     */
    public static function locked(): self
    {
        if (DB::transactionLevel() === 0) {
            throw LedgerViolation::notInTransaction();
        }

        static::current();

        return static::query()->whereKey(1)->lockForUpdate()->firstOrFail();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }
}
