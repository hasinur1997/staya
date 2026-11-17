<?php

namespace App\Http\Controllers\Meals;

use App\Actions\Meals\RecordDayMeals;
use App\Actions\Reports\CalculateMonthlyReport;
use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\RecordMealsRequest;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MonthClosure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    /**
     * The day screen: every member who lived in the mess that day with their
     * meal counts. Users who may only see their own meals are sent to them.
     */
    public function index(Request $request, CalculateMonthlyReport $calculate): Response|RedirectResponse
    {
        $actor = $request->user();

        if (Gate::denies('viewAll', Meal::class)) {
            abort_unless($actor->can('viewOwn', Meal::class), 403);

            return to_route('meals.mine');
        }

        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $today = BusinessCalendar::today()->toDateString();
        $date = min($request->string('date')->toString() ?: $today, $today);

        $meals = Meal::query()->where('meal_date', $date)->get(['member_id', 'meal_type_id', 'count']);
        $usedTypeIds = $meals->pluck('meal_type_id')->unique()->all();
        $types = MealType::query()->ordered()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $usedTypeIds))
            ->get();

        $counts = [];

        foreach ($meals as $meal) {
            $counts[$meal->member_id][$meal->meal_type_id] = $meal->count;
        }

        $members = Member::query()
            ->whereHas('stays', fn (Builder $stays) => $stays->overlapping($date, $date))
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'name_bn']);

        $month = $calculate->handle((int) substr($date, 0, 4), (int) substr($date, 5, 2));

        return Inertia::render('meals/index', [
            'date' => $date,
            'today' => $today,
            'types' => $types->map(fn (MealType $type): array => ['id' => $type->id, 'name' => $type->name, 'is_active' => $type->is_active])->all(),
            'members' => $members->map(fn (Member $member): array => [
                'id' => $member->id,
                'name' => $member->full_name,
                'counts' => (object) ($counts[$member->id] ?? []),
            ])->all(),
            'dayTotals' => $meals->groupBy('meal_type_id')->map(fn ($group): int => (int) $group->sum('count'))->all() ?: (object) [],
            'month' => [
                'meals' => $month->totalMeals,
                'expenses' => $month->expenses,
                'rate' => $month->totalMeals > 0 ? bcdiv($month->expenses, (string) $month->totalMeals, 2) : null,
                'split' => $month->split->value,
            ],
            'monthClosed' => MonthClosure::isClosed($date),
            'maxPerType' => Meal::MAX_PER_TYPE,
            'can' => [
                'record' => $actor->can('record', Meal::class),
                'viewOwn' => $actor->can('viewOwn', Meal::class),
                'manageTypes' => $actor->can('viewAny', MealType::class),
                'viewSheet' => $actor->can('viewAll', Meal::class),
            ],
        ]);
    }

    public function store(RecordMealsRequest $request, RecordDayMeals $recordDayMeals): RedirectResponse
    {
        $changed = $recordDayMeals->handle($request->user(), $request->string('date')->toString(), $request->entries());

        Inertia::flash('toast', ['type' => 'success', 'message' => $changed === 0
            ? __('Nothing changed.')
            : __('Meals saved.')]);

        return back();
    }

    /**
     * The viewer's own meals for a month, with what they cost.
     */
    public function mine(Request $request, CalculateMonthlyReport $calculate): Response
    {
        Gate::authorize('viewOwn', Meal::class);

        $member = $request->user()->member;
        abort_if($member === null, 403);

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $current = BusinessCalendar::today()->format('Y-m');
        $month = min($request->string('month')->toString() ?: $current, $current);
        [$first, $last] = BusinessCalendar::monthDates((int) substr($month, 0, 4), (int) substr($month, 5, 2));

        $types = MealType::query()->ordered()->get();
        $days = [];

        foreach (Meal::query()->where('member_id', $member->id)->whereBetween('meal_date', [$first, $last])->orderBy('meal_date')->get() as $meal) {
            $days[$meal->meal_date->toDateString()][$meal->meal_type_id] = $meal->count;
        }

        $report = $calculate->handle((int) substr($month, 0, 4), (int) substr($month, 5, 2));
        $row = $report->rowFor($member->id);

        return Inertia::render('meals/mine', [
            'month' => $month,
            'currentMonth' => $current,
            'types' => $types->filter(fn (MealType $type): bool => $type->is_active || collect($days)->contains(fn (array $day): bool => isset($day[$type->id])))
                ->map(fn (MealType $type): array => ['id' => $type->id, 'name' => $type->name])->values()->all(),
            'days' => collect($days)->map(fn (array $counts, string $date): array => ['date' => $date, 'counts' => $counts])->values()->all(),
            'summary' => [
                'meals' => $row['meals'] ?? 0,
                'total_meals' => $report->totalMeals,
                'rate' => $report->totalMeals > 0 ? bcdiv($report->expenses, (string) $report->totalMeals, 2) : null,
                'meal_cost' => bcdiv(bcmul(Money::numeric($report->expenses), (string) ($row['meals'] ?? 0), 2), (string) max($report->totalMeals, 1), 2),
                'expenses' => $report->expenses,
                'split' => $report->split->value,
                'share' => $row['share'] ?? '0.00',
            ],
        ]);
    }

    /**
     * The monthly meal sheet: one row per member, one column per day, with
     * row and column totals. Optionally limited to one meal type.
     */
    public function sheet(Request $request, CalculateMonthlyReport $calculate): Response
    {
        Gate::authorize('viewAll', Meal::class);

        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'type' => ['nullable', 'integer', Rule::exists(MealType::class, 'id')],
        ]);

        $current = BusinessCalendar::today()->format('Y-m');
        $month = min($request->string('month')->toString() ?: $current, $current);
        $year = (int) substr($month, 0, 4);
        $number = (int) substr($month, 5, 2);
        [$first, $last] = BusinessCalendar::monthDates($year, $number);
        $typeId = $request->filled('type') ? $request->integer('type') : null;

        $perMember = [];
        $perDay = [];
        $grand = 0;

        $query = Meal::query()->whereBetween('meal_date', [$first, $last])
            ->when($typeId, fn (Builder $meals, int $id) => $meals->where('meal_type_id', $id));

        foreach ($query->get(['member_id', 'meal_date', 'count']) as $meal) {
            $day = (int) $meal->meal_date->format('j');
            $perMember[$meal->member_id][$day] = ($perMember[$meal->member_id][$day] ?? 0) + $meal->count;
            $perDay[$day] = ($perDay[$day] ?? 0) + $meal->count;
            $grand += $meal->count;
        }

        $report = $calculate->handle($year, $number);
        $daysInMonth = (int) substr($last, 8, 2);

        return Inertia::render('meals/sheet', [
            'month' => $month,
            'currentMonth' => $current,
            'typeId' => $typeId,
            'types' => MealType::query()->ordered()->get(['id', 'name'])->map(fn (MealType $type): array => ['id' => $type->id, 'name' => $type->name])->all(),
            'days' => array_map(fn (int $day): array => [
                'day' => $day,
                'weekday' => CarbonImmutable::create($year, $number, $day)->dayOfWeek,
                'total' => $perDay[$day] ?? 0,
            ], range(1, $daysInMonth)),
            'rows' => array_map(fn (array $row): array => [
                'member_id' => $row['member_id'],
                'name' => $row['name'],
                'counts' => (object) ($perMember[$row['member_id']] ?? []),
                'total' => array_sum($perMember[$row['member_id']] ?? []),
                // Cost is of all meals, so it is left out when one type is shown.
                'cost' => $typeId === null && $report->totalMeals > 0
                    ? bcdiv(bcmul(Money::numeric($report->expenses), (string) $row['meals'], 2), (string) $report->totalMeals, 2)
                    : null,
            ], $report->members),
            'total' => $grand,
            'figures' => [
                'expenses' => $report->expenses,
                'total_meals' => $report->totalMeals,
                'rate' => $report->totalMeals > 0 ? bcdiv(Money::numeric($report->expenses), (string) $report->totalMeals, 2) : null,
            ],
            'can' => ['record' => $request->user()->can('record', Meal::class)],
        ]);
    }
}
