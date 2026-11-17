<?php

namespace App\Http\Controllers\Meals;

use App\Actions\Meals\SaveMealType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\SaveMealTypeRequest;
use App\Models\Meal;
use App\Models\MealType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The kinds of meal served in a day. They can be added, renamed and switched
 * off; there is no delete route.
 */
class MealTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MealType::class);

        return Inertia::render('meals/types', [
            'types' => MealType::query()->ordered()->withCount('meals')->get()->map(fn (MealType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'sort_order' => $type->sort_order,
                'is_active' => $type->is_active,
                'has_meals' => $type->meals_count > 0,
            ])->all(),
            'can' => ['manage' => $request->user()->can('manage', MealType::class)],
            'maxPerType' => Meal::MAX_PER_TYPE,
        ]);
    }

    public function store(SaveMealTypeRequest $request, SaveMealType $saveMealType): RedirectResponse
    {
        $saveMealType->handle(null, $request->values());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meal type added.')]);

        return back();
    }

    public function update(SaveMealTypeRequest $request, MealType $mealType, SaveMealType $saveMealType): RedirectResponse
    {
        $saveMealType->handle($mealType, $request->values());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meal type saved.')]);

        return back();
    }
}
