<?php

namespace App\Http\Controllers\Audit;

use App\Concerns\PresentsAudit;
use App\Enums\AuditAction;
use App\Enums\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\IndexAuditLogRequest;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The read-only audit viewer. There is no store, update or destroy route,
 * and the Activity model refuses both as well.
 */
class AuditLogController extends Controller
{
    use PresentsAudit;

    public function index(IndexAuditLogRequest $request): Response
    {
        $filters = $request->filters();
        ['start' => $start, 'end' => $end] = $request->range();

        $activities = Activity::query()
            ->with('causer:id,name')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->when($filters['actor'], fn ($query, int $actor) => $query->where('causer_type', 'user')->where('causer_id', $actor))
            ->when($filters['log'], fn ($query, string $log) => $query->where('log_name', $log))
            ->when($filters['action'], fn ($query, string $action) => $query->where('description', $action))
            ->when($filters['subject'], fn ($query, string $subject) => $query->where('subject_type', $subject))
            ->when($filters['subject_id'], fn ($query, int $id) => $query->where('subject_id', $id))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activity $activity): array => $this->auditRow($activity));

        return Inertia::render('audit/index', [
            'activities' => $activities,
            'filters' => $filters,
            'actors' => User::query()->whereIn('id', Activity::query()->where('causer_type', 'user')->select('causer_id'))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all(),
            'logs' => array_map(fn (AuditLog $log): array => ['value' => $log->value, 'label' => $log->label()], AuditLog::cases()),
            'actions' => Activity::query()->distinct()->orderBy('description')->pluck('description')
                ->map(fn (string $description): array => ['value' => $description, 'label' => AuditAction::labelFor($description)])->all(),
            'subjects' => Activity::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')
                ->map(fn (string $type): array => ['value' => $type, 'label' => (string) $this->subjectLabel($type)])->all(),
        ]);
    }

    public function show(Request $request, Activity $activity): Response
    {
        Gate::authorize('view', $activity);

        $activity->load('causer:id,name');

        return Inertia::render('audit/show', [
            'activity' => [
                ...$this->auditRow($activity),
                'diff' => $this->auditDiff($activity),
                'subject_url' => $this->subjectUrl($activity->subject_type, $activity->subject_id, $request->user()),
                'properties' => collect($activity->properties)->except('email_tried')->all(),
            ],
        ]);
    }
}
