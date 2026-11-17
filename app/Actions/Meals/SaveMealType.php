<?php

namespace App\Actions\Meals;

use App\Enums\AuditLog;
use App\Models\MealType;
use Illuminate\Support\Facades\DB;

class SaveMealType
{
    /**
     * Create a meal type, or change one. Types are never deleted: switching
     * one off hides it from the entry screen and keeps its history.
     * Authorization happens in the form request.
     *
     * @param  array{name: string, sort_order: int, is_active: bool}  $values
     */
    public function handle(?MealType $type, array $values): MealType
    {
        return DB::transaction(function () use ($type, $values): MealType {
            $before = $type?->auditSnapshot();
            $type ??= new MealType;
            $type->fill($values);

            if ($type->exists && ! $type->isDirty()) {
                return $type;
            }

            $type->save();

            activity()
                ->useLog(AuditLog::Meal)
                ->performedOn($type)
                ->event($before === null ? 'created' : 'updated')
                ->withChanges(array_filter(['old' => $before, 'attributes' => $type->auditSnapshot()]))
                ->log($before === null ? 'meal_type_created' : 'meal_type_updated');

            return $type;
        });
    }
}
