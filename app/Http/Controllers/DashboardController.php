<?php

namespace App\Http\Controllers;

use App\Actions\Members\SummarizeMemberAccount;
use App\Actions\Reports\BuildDashboardInsights;
use App\Actions\Reports\CalculateMonthlyReport;
use App\Actions\Reports\MonthlyReport;
use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Concerns\PresentsDeposits;
use App\Concerns\PresentsPurchases;
use App\Enums\DepositStatus;
use App\Enums\MemberStatus;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home screen. Members see their own month, what the fund owes them and
 * their latest deposits and purchases; users with `report.view_all` also see
 * every member's figures; reviewers see what is waiting for them. Create
 * links are mirrored from the policies, which still guard the endpoints.
 */
class DashboardController extends Controller
{
    use PresentsDeposits, PresentsPurchases;

    public function __invoke(Request $request, CalculateMonthlyReport $calculate, SummarizeMemberAccount $summarizeAccount, BuildDashboardInsights $buildInsights): Response
    {
        $actor = $request->user();
        $member = $actor->member;

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $current = BusinessCalendar::today()->format('Y-m');
        $month = min($request->string('month')->toString() ?: $current, $current);
        $report = Gate::allows('viewOwn', MonthlyReport::class)
            ? $calculate->handle((int) substr($month, 0, 4), (int) substr($month, 5, 2))
            : null;
        $viewAll = Gate::allows('viewAll', MonthlyReport::class);
        [$monthFirst, $monthLast] = BusinessCalendar::monthDates((int) substr($month, 0, 4), (int) substr($month, 5, 2));
        $account = $member !== null ? $summarizeAccount->handle($member, $monthFirst, $monthLast) : null;

        return Inertia::render('dashboard', [
            'month' => $month,
            'currentMonth' => $current,
            'serverNow' => now()->toIso8601String(),
            'memberStatus' => $member?->status->value,
            'summary' => $report !== null && $member !== null ? $report->rowFor($member->id) : null,
            'report' => $report !== null && $viewAll ? [
                'deposits' => $report->deposits,
                'expenses' => $report->expenses,
                'split' => $report->split->value,
                'active_members' => $report->activeMembers,
                'total_meals' => $report->totalMeals,
                'share' => $report->share,
                'leftover' => $report->leftover,
                'members' => $report->members,
            ] : null,
            'insights' => $report !== null && $viewAll ? $buildInsights->handle($month) : null,
            'myTrend' => $report !== null && $member !== null ? $this->ownTrend($calculate, $member, $month) : null,
            'account' => $account === null ? null : [
                'deposited' => $actor->can('viewAny', Deposit::class) ? $account['deposited'] : null,
                'bajar' => $actor->can('viewAny', GroceryPurchase::class) ? $account['bajar'] : null,
            ],
            'owed' => $member !== null ? $this->owedTo($member) : null,
            'recentDeposits' => $member !== null && $actor->can('viewAny', Deposit::class)
                ? Deposit::query()->where('member_id', $member->id)->with('member:id,first_name,last_name,user_id')
                    ->latest('deposit_date')->latest('id')->limit(5)->get()
                    ->map(fn (Deposit $deposit): array => $this->depositRow($deposit, $actor))->all()
                : [],
            'recentPurchases' => $member !== null && $actor->can('viewAny', GroceryPurchase::class)
                ? GroceryPurchase::query()->active()->where('buyer_member_id', $member->id)
                    ->with(['buyer:id,first_name,last_name,user_id', 'payer:id,first_name,last_name', 'items'])
                    ->latest('purchase_date')->latest('id')->limit(5)->get()
                    ->map(fn (GroceryPurchase $purchase): array => $this->purchaseRow($purchase, $actor))->all()
                : [],
            'queues' => [
                'pendingDeposits' => $actor->can('review', Deposit::class)
                    ? Deposit::query()->where('status', DepositStatus::Pending)->count()
                    : null,
                'pendingRegistrations' => $actor->can('viewAnyRegistrations', Member::class)
                    ? Member::query()->where('status', MemberStatus::Pending)->count()
                    : null,
                'openPayables' => $actor->can('viewAny', BuyerPayable::class)
                    ? BuyerPayable::query()->open()->count()
                    : null,
            ],
            'can' => [
                'createDeposit' => $actor->can('create', Deposit::class),
                'createPurchase' => $actor->can('create', GroceryPurchase::class) || $actor->can('createForOthers', GroceryPurchase::class),
                'viewPurchases' => $actor->can('viewAny', GroceryPurchase::class),
                'viewDeposits' => $actor->can('viewAny', Deposit::class),
            ],
        ]);
    }

    /**
     * The viewer's own deposits and share for the six months up to the one
     * shown; months they did not live in the mess are left out.
     *
     * @return list<array{month: string, deposits: string, share: string, due_credit: string}>
     */
    private function ownTrend(CalculateMonthlyReport $calculate, Member $member, string $month): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $month, BusinessCalendar::TIMEZONE);
        $rows = [];

        for ($step = BuildDashboardInsights::MONTHS - 1; $step >= 0; $step--) {
            $at = $end->subMonthsNoOverflow($step);
            $row = $calculate->handle($at->year, $at->month)->rowFor($member->id);

            if ($row !== null) {
                $rows[] = ['month' => $at->format('Y-m'), 'deposits' => $row['deposits'], 'share' => $row['share'], 'due_credit' => $row['due_credit']];
            }
        }

        return $rows;
    }

    /**
     * What the fund still owes the member for purchases it could not cover
     * or that they paid for themselves.
     */
    private function owedTo(Member $member): string
    {
        $owed = '0.00';

        foreach (BuyerPayable::query()->open()->where('member_id', $member->id)->get() as $payable) {
            $owed = bcadd($owed, Money::numeric($payable->outstanding()), Money::SCALE);
        }

        return $owed;
    }
}
