<?php

namespace App\Actions\Reports;

use App\Concerns\BusinessCalendar;
use App\Enums\AuditLog;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a finished month: from then on nothing dated in it can be created
 * or changed (see MonthClosure::assertOpen). A month closed again after a
 * reopen reuses its row; the history lives in the activity log.
 */
class CloseMonth
{
    /**
     * @param  string  $month  `Y-m`
     *
     * @throws ValidationException
     */
    public function handle(User $actor, string $month): MonthClosure
    {
        $first = MonthClosure::monthOf($month.'-01');

        if ($first->toDateString() >= BusinessCalendar::today()->startOfMonth()->toDateString()) {
            throw ValidationException::withMessages(['month' => __('Only a finished month can be closed.')]);
        }

        return DB::transaction(function () use ($actor, $first): MonthClosure {
            $closure = MonthClosure::query()->where('month', $first->toDateString())->lockForUpdate()->first();

            if ($closure !== null && $closure->reopened_at === null) {
                throw ValidationException::withMessages(['month' => __('This month is already closed.')]);
            }

            $before = $closure === null ? null : $this->snapshot($closure);

            $closure ??= new MonthClosure(['month' => $first->toDateString()]);
            $closure->forceFill([
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'reopened_by' => null,
                'reopened_at' => null,
                'reopen_reason' => null,
                'cost_split' => MessSetting::current()->cost_split,
            ])->save();

            activity()
                ->useLog(AuditLog::Report)
                ->performedOn($closure)
                ->event('month_closed')
                ->withChanges(['old' => $before, 'attributes' => $this->snapshot($closure)])
                ->log('month_closed');

            return $closure;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(MonthClosure $closure): array
    {
        return [
            'month' => $closure->month->format('Y-m'),
            'status' => $closure->reopened_at === null ? 'closed' : 'reopened',
            'reopen_reason' => $closure->reopen_reason,
            'cost_split' => $closure->cost_split?->value,
        ];
    }
}
