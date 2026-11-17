<?php

namespace App\Http\Controllers\Members;

use App\Actions\Members\CreateMember;
use App\Actions\Members\DeactivateMember;
use App\Actions\Members\DeleteMember;
use App\Actions\Members\ReactivateMember;
use App\Actions\Members\SummarizeMemberBalance;
use App\Actions\Members\UpdateMember;
use App\Actions\Reports\CalculateMonthlyReport;
use App\Actions\Reports\MonthlyReport;
use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Concerns\PresentsAudit;
use App\Concerns\PresentsDeposits;
use App\Concerns\PresentsMembers;
use App\Concerns\PresentsPurchases;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StayDateRequest;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Requests\Members\UpdateMemberSectionRequest;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use PresentsAudit, PresentsDeposits, PresentsMembers, PresentsPurchases;

    /**
     * Show the member list.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);

        $status = $request->enum('status', MemberStatus::class);
        $search = $request->string('search')->trim()->toString();

        $members = Member::query()
            ->with(['user:id,email', 'latestStay'])
            ->when($status, fn (Builder $query, MemberStatus $status) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('name_bn', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                'email' => $member->user->email,
                'phone' => $member->phone,
                'status' => $member->status->value,
                'status_label' => $member->status->label(),
                'joined_on' => $member->latestStay?->joined_on->toDateString(),
                'left_on' => $member->latestStay?->left_on?->toDateString(),
            ]);

        return Inertia::render('members/index', [
            'members' => $members,
            'filters' => ['status' => $status?->value, 'search' => $search],
            'statuses' => MemberStatus::options(),
            'can' => [
                'create' => $request->user()->can('create', Member::class),
            ],
        ]);
    }

    /**
     * Show the form for a new member.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Member::class);

        $actor = $request->user();

        // Accounts without a member profile that the actor may take over.
        $linkableUsers = User::query()
            ->whereDoesntHave('member')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $actor->can('update', $user))
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values();

        return Inertia::render('members/create', [
            'linkableUsers' => $linkableUsers,
            'today' => BusinessCalendar::today()->toDateString(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Store a new member.
     */
    public function store(StoreMemberRequest $request, CreateMember $createMember): RedirectResponse
    {
        $member = $createMember->handle(
            $request->user(),
            $request->details(),
            $request->account(),
            $request->string('joined_on')->toString(),
            $request->nidFile(),
            $request->photo(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member registered.')]);

        return to_route('members.show', $member);
    }

    /**
     * Show a member's profile.
     */
    public function show(Request $request, Member $member, SummarizeMemberBalance $summarizeBalance): Response
    {
        Gate::authorize('view', $member);

        $actor = $request->user();
        $tab = $request->query('tab');
        $tab = in_array($tab, ['profile', 'meals', 'purchases', 'deposits', 'balance'], true) ? $tab : 'profile';
        $monthly = $this->monthlyHistory($member, $actor);

        return Inertia::render('members/show', [
            'member' => $this->memberProfile($member, $actor),
            ...$this->nidProp($member, $actor, 'profile'),
            'tab' => $tab,
            'deposits' => $this->depositHistory($member, $actor),
            'purchases' => $this->purchaseHistory($member, $actor),
            'monthly' => $monthly,
            'meals' => $tab === 'meals' ? $this->mealSheet($request, $member) : null,
            'balance' => $tab === 'balance' && $monthly !== null ? $summarizeBalance->handle($member) : null,
            'balanceVisible' => $monthly !== null,
            'mealsVisible' => $this->canViewMeals($member, $actor),
            'history' => $this->auditHistory($member, $actor),
            'today' => BusinessCalendar::today()->toDateString(),
            'can' => [
                'update' => $actor->can('update', $member),
                'changeEmail' => $actor->can('changeEmail', $member),
                'deactivate' => $actor->can('deactivate', $member),
                'reactivate' => $actor->can('reactivate', $member),
                'delete' => $actor->can('delete', $member),
                'viewNid' => $actor->can('viewNid', $member),
                'viewAny' => $actor->can('viewAny', Member::class),
            ],
        ]);
    }

    /**
     * Edit one card of the profile.
     */
    public function updateSection(UpdateMemberSectionRequest $request, Member $member, string $section, UpdateMember $updateMember): RedirectResponse
    {
        $updateMember->handle(
            $member,
            $request->details(),
            $request->email(),
            $request->nidFile(),
            $request->photo(),
            $request->banner(),
            $request->removesBanner(),
            $request->removesPhoto(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return back();
    }

    private function canViewMeals(Member $member, User $viewer): bool
    {
        return $viewer->can('viewAll', Meal::class)
            || ($member->user_id === $viewer->id && $viewer->can('viewOwn', Meal::class));
    }

    /**
     * One member's meals for a month: a row per day with the count of each
     * meal type, totals, and the cost of their meals. Null hides the tab.
     *
     * @return array<string, mixed>|null
     */
    private function mealSheet(Request $request, Member $member): ?array
    {
        if (! $this->canViewMeals($member, $request->user())) {
            return null;
        }

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $current = BusinessCalendar::today()->format('Y-m');
        $month = min($request->string('month')->toString() ?: $current, $current);
        $year = (int) substr($month, 0, 4);
        $number = (int) substr($month, 5, 2);
        [$first, $last] = BusinessCalendar::monthDates($year, $number);

        $counts = [];
        $perType = [];

        foreach ($member->meals()->whereBetween('meal_date', [$first, $last])->get(['meal_type_id', 'meal_date', 'count']) as $meal) {
            $day = (int) $meal->meal_date->format('j');
            $counts[$day][$meal->meal_type_id] = $meal->count;
            $perType[$meal->meal_type_id] = ($perType[$meal->meal_type_id] ?? 0) + $meal->count;
        }

        $types = MealType::query()->ordered()->get()
            ->filter(fn (MealType $type): bool => $type->is_active || isset($perType[$type->id]))
            ->map(fn (MealType $type): array => ['id' => $type->id, 'name' => $type->name])
            ->values();

        $report = app(CalculateMonthlyReport::class)->handle($year, $number);
        $row = $report->rowFor($member->id);
        $meals = $row['meals'] ?? array_sum($perType);

        return [
            'month' => $month,
            'currentMonth' => $current,
            'types' => $types->all(),
            'days' => array_map(fn (int $day): array => [
                'date' => sprintf('%s-%02d', $month, $day),
                'weekday' => CarbonImmutable::create($year, $number, $day)->dayOfWeek,
                'counts' => (object) ($counts[$day] ?? []),
                'total' => array_sum($counts[$day] ?? []),
            ], range(1, (int) substr($last, 8, 2))),
            'typeTotals' => (object) $perType,
            'total' => array_sum($perType),
            'figures' => [
                'expenses' => $report->expenses,
                'total_meals' => $report->totalMeals,
                'rate' => $report->totalMeals > 0 ? bcdiv(Money::numeric($report->expenses), (string) $report->totalMeals, 2) : null,
                'cost' => $report->totalMeals > 0
                    ? bcdiv(bcmul(Money::numeric($report->expenses), (string) $meals, 2), (string) $report->totalMeals, 2)
                    : null,
            ],
            'can' => ['record' => $request->user()->can('record', Meal::class)],
        ];
    }

    /**
     * The member's deposits, share and due or credit for the last six
     * months they lived in the mess, newest first. Each month stands alone
     * (no carry-forward). Null hides the section from viewers without
     * `report.view_all` who are not looking at their own profile.
     *
     * @return array<int, array{month: string, deposits: string, share: string, due_credit: string}>|null
     */
    private function monthlyHistory(Member $member, User $viewer): ?array
    {
        $visible = Gate::forUser($viewer)->allows('viewAll', MonthlyReport::class)
            || ($member->user_id === $viewer->id && Gate::forUser($viewer)->allows('viewOwn', MonthlyReport::class));

        if (! $visible) {
            return null;
        }

        $calculate = app(CalculateMonthlyReport::class);
        $rows = [];
        $cursor = BusinessCalendar::today()->startOfMonth();

        for ($step = 0; $step < 6; $step++) {
            $month = $cursor->subMonthsNoOverflow($step);
            $row = $calculate->handle($month->year, $month->month)->rowFor($member->id);

            if ($row !== null) {
                $rows[] = [
                    'month' => $month->format('Y-m'),
                    'deposits' => $row['deposits'],
                    'share' => $row['share'],
                    'due_credit' => $row['due_credit'],
                ];
            }
        }

        return $rows;
    }

    /**
     * The member's latest deposits, for viewers allowed to see them; null
     * hides the section.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function depositHistory(Member $member, User $viewer): ?array
    {
        $visible = $viewer->can('viewAll', Deposit::class)
            || ($member->user_id === $viewer->id && $viewer->can('viewAny', Deposit::class));

        if (! $visible) {
            return null;
        }

        return $member->deposits()
            ->with('member:id,first_name,last_name,name_bn,user_id')
            ->orderByDesc('deposit_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Deposit $deposit) => $this->depositRow($deposit, $viewer))
            ->all();
    }

    /**
     * The latest purchases the member made, for viewers allowed to see
     * purchases; null hides the section. Voided ones are left out.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function purchaseHistory(Member $member, User $viewer): ?array
    {
        if (! $viewer->can('viewAny', GroceryPurchase::class)) {
            return null;
        }

        return $member->purchases()
            ->active()
            ->with(['buyer:id,first_name,last_name,name_bn,user_id', 'payer:id,first_name,last_name,name_bn', 'items'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (GroceryPurchase $purchase) => $this->purchaseRow($purchase, $viewer))
            ->all();
    }

    /**
     * Show the form for editing a member.
     */
    public function edit(Request $request, Member $member): Response
    {
        Gate::authorize('update', $member);

        $actor = $request->user();

        return Inertia::render('members/edit', [
            'member' => $this->memberProfile($member, $actor),
            ...$this->nidProp($member, $actor, 'edit'),
            'can' => [
                'changeNid' => $actor->can('viewNid', $member),
                'changeEmail' => $actor->can('changeEmail', $member),
            ],
        ]);
    }

    /**
     * Update a member.
     */
    public function update(UpdateMemberRequest $request, Member $member, UpdateMember $updateMember): RedirectResponse
    {
        $updateMember->handle(
            $member,
            $request->details(),
            $request->string('email')->toString(),
            $request->nidFile(),
            $request->photo(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return to_route('members.show', $member);
    }

    /**
     * Hard-delete a member without financial records.
     */
    public function destroy(Request $request, Member $member, DeleteMember $deleteMember): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $account = $deleteMember->handle($request->user(), $member);

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($account) {
            'deleted' => __('Member and their account deleted.'),
            'deactivated' => __('Member deleted. Their account was deactivated because other records refer to it.'),
            'kept' => __('Member deleted. Their staff account was kept.'),
        }]);

        return to_route('members.index');
    }

    /**
     * Record that a member left.
     */
    public function deactivate(StayDateRequest $request, Member $member, DeactivateMember $deactivateMember): RedirectResponse
    {
        $deactivateMember->handle($request->user(), $member, $request->stayDate());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member deactivated.')]);

        return to_route('members.show', $member);
    }

    /**
     * Record that a member rejoined.
     */
    public function reactivate(StayDateRequest $request, Member $member, ReactivateMember $reactivateMember): RedirectResponse
    {
        $reactivateMember->handle($request->user(), $member, $request->stayDate());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member reactivated.')]);

        return to_route('members.show', $member);
    }
}
