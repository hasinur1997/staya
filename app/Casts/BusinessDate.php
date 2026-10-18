<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A calendar date (Asia/Dhaka business day) stored as a plain `Y-m-d`.
 *
 * Laravel's `date` cast stores `Y-m-d H:i:s`, which SQLite keeps verbatim,
 * so `where('entry_date', '2026-10-01')` would miss rows. This cast keeps
 * the stored value comparable as a string on every database.
 *
 * @implements CastsAttributes<CarbonImmutable, string|DateTimeInterface>
 */
class BusinessDate implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10)) ?: null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 => substr($value, 0, 10),
            default => throw new InvalidArgumentException("[{$key}] must be a Y-m-d date."),
        };
    }
}
