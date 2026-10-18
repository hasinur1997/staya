<?php

namespace App\Models;

use App\Exceptions\AppendOnlyViolation;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * An audit-log row. Append-only: it records the request IP on insert and
 * refuses every update or delete made through the model.
 *
 * @property string|null $ip_address
 */
class Activity extends SpatieActivity
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $activity->ip_address ??= request()->ip();
        });

        static::updating(fn (Activity $activity) => throw AppendOnlyViolation::for($activity->getTable()));
        static::deleting(fn (Activity $activity) => throw AppendOnlyViolation::for($activity->getTable()));
    }
}
