<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildReport;
use App\Actions\Reports\CloseMonth;
use App\Actions\Reports\MonthlyReport;
use App\Actions\Reports\ReopenMonth;
use App\Concerns\BusinessCalendar;
use App\Http\Requests\Reports\CloseMonthRequest;
use App\Http\Requests\Reports\ReopenMonthRequest;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The monthly report page and month closing. Closing and reopening are
 * guarded by `report.close_month` through the ReportPolicy.
 */
class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, BuildReport $build): Response
    {
        $actor = $request->user();
        [$from, $to, $month] = $request->period();
        $memberId = Gate::allows('viewAll', MonthlyReport::class) && $request->filled('member') ? $request->integer('member') : null;
        $report = $build->handle($actor, $from, $to, $month, $memberId);

        $finished = $month !== null && $month < BusinessCalendar::today()->format('Y-m');
        $closed = ($report['closure']['status'] ?? null) === 'closed';
        $canClose = Gate::allows('closeMonth', MonthlyReport::class);

        return Inertia::render('reports/index', [
            'report' => $report,
            'filters' => ['month' => $month, 'from' => $month === null ? $from : null, 'to' => $month === null ? $to : null, 'member' => $memberId],
            'members' => Gate::allows('viewAll', MonthlyReport::class)
                ? Member::withTrashed()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'name_bn', 'status'])
                    ->map(fn (Member $member): array => ['id' => $member->id, 'name' => $member->full_name, 'status' => $member->status->value])->all()
                : [],
            'currentMonth' => BusinessCalendar::today()->format('Y-m'),
            'can' => [
                'viewAll' => Gate::allows('viewAll', MonthlyReport::class),
                'close' => $canClose && $finished && ! $closed,
                'reopen' => $canClose && $closed,
            ],
        ]);
    }

    public function close(CloseMonthRequest $request, CloseMonth $action): RedirectResponse
    {
        $action->handle($request->user(), $request->month());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Month closed. Nothing dated in it can change now.')]);

        return back();
    }

    public function reopen(ReopenMonthRequest $request, ReopenMonth $action): RedirectResponse
    {
        $action->handle($request->user(), $request->month(), $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Month reopened.')]);

        return back();
    }
}
