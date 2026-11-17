<?php

namespace App\Actions\Meals;

use App\Concerns\BusinessCalendar;
use App\Enums\AuditLog;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDayMeals
{
    /**
     * Save the meal counts of one day. `$entries` maps member id to meal type
     * id to a count (0 to 10); a count that equals what is saved is skipped
     * and a zero removes the row. Only members who lived in the mess that
     * day can have meals, switched-off types cannot be changed, and a closed
     * month is refused. Everything changed is logged in one entry.
     * Authorization happens in the form request.
     *
     * @param  array<int, array<int, int>>  $entries
     * @return int How many counts changed.
     *
     * @throws ValidationException
     */
    public function handle(User $actor, string $date, array $entries): int
    {
        if ($date > BusinessCalendar::today()->toDateString()) {
            throw ValidationException::withMessages(['date' => __('Meals cannot be recorded for a future date.')]);
        }

        MonthClosure::assertOpen($date, 'date');

        return DB::transaction(function () use ($actor, $date, $entries): int {
            $members = Member::query()
                ->whereHas('stays', fn (Builder $stays) => $stays->overlapping($date, $date))
                ->get(['id', 'first_name', 'last_name', 'name_bn'])
                ->keyBy('id');
            $types = MealType::query()->get()->keyBy('id');
            $saved = Meal::query()->where('meal_date', $date)->lockForUpdate()->get()
                ->keyBy(fn (Meal $meal): string => $meal->member_id.'-'.$meal->meal_type_id);

            $old = [];
            $new = [];

            foreach ($entries as $memberId => $counts) {
                $member = $members->get($memberId);

                if ($member === null) {
                    throw ValidationException::withMessages(['entries' => __('One of the members did not live in the mess on this day.')]);
                }

                foreach ($counts as $typeId => $count) {
                    $type = $types->get($typeId);
                    $meal = $saved->get($memberId.'-'.$typeId);
                    $current = $meal instanceof Meal ? $meal->count : 0;

                    if ($type === null) {
                        throw ValidationException::withMessages(['entries' => __('One of the meal types does not exist.')]);
                    }

                    if ($count === $current) {
                        continue;
                    }

                    if (! $type->is_active) {
                        throw ValidationException::withMessages(['entries' => __(':type is switched off, so its meals cannot be changed.', ['type' => $type->name])]);
                    }

                    if ($count === 0) {
                        if ($meal instanceof Meal) {
                            $meal->delete();
                        }
                    } elseif ($meal instanceof Meal) {
                        $meal->forceFill(['count' => $count, 'recorded_by' => $actor->id])->save();
                    } else {
                        $created = new Meal(['member_id' => $memberId, 'meal_type_id' => $typeId, 'meal_date' => $date, 'count' => $count, 'recorded_by' => $actor->id]);
                        $created->save();
                    }

                    $label = $member->full_name.' / '.$type->name;
                    $old[$label] = $current;
                    $new[$label] = $count;
                }
            }

            if ($new === []) {
                return 0;
            }

            activity()
                ->useLog(AuditLog::Meal)
                ->event('updated')
                ->withProperties(['date' => $date])
                ->withChanges(['old' => $old, 'attributes' => $new])
                ->log('meals_recorded');

            return count($new);
        });
    }
}
