<?php

namespace App\Actions\Reports;

use App\Enums\AuditLog;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reopens a closed month so it can change again. A reason is required and
 * is kept on the closure and in the activity log.
 */
class ReopenMonth
{
    /**
     * @param  string  $month  `Y-m`
     *
     * @throws ValidationException
     */
    public function handle(User $actor, string $month, string $reason): MonthClosure
    {
        $first = MonthClosure::monthOf($month.'-01');

        return DB::transaction(function () use ($actor, $first, $reason): MonthClosure {
            $closure = MonthClosure::query()->closed()->where('month', $first->toDateString())->lockForUpdate()->first();

            if ($closure === null) {
                throw ValidationException::withMessages(['month' => __('This month is not closed.')]);
            }

            $closure->forceFill([
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ])->save();

            activity()
                ->useLog(AuditLog::Report)
                ->performedOn($closure)
                ->event('month_reopened')
                ->withChanges([
                    'old' => ['month' => $first->format('Y-m'), 'status' => 'closed'],
                    'attributes' => ['month' => $first->format('Y-m'), 'status' => 'reopened', 'reopen_reason' => $reason],
                ])
                ->log('month_reopened');

            return $closure;
        });
    }
}
