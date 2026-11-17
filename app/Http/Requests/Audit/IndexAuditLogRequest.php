<?php

namespace App\Http\Requests\Audit;

use App\Concerns\BusinessCalendar;
use App\Enums\AuditLog;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Audit log filters: date range (Asia/Dhaka days, default the last 30),
 * actor, log, action and subject.
 */
class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', Activity::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'actor' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'log' => ['nullable', Rule::enum(AuditLog::class)],
            'action' => ['nullable', 'string', 'max:64'],
            'subject' => ['nullable', 'string', 'max:64'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{from: string, to: string, actor: int|null, log: string|null, action: string|null, subject: string|null, subject_id: int|null}
     */
    public function filters(): array
    {
        $today = BusinessCalendar::today();

        return [
            'from' => $this->filled('from') ? $this->string('from')->toString() : $today->subDays(29)->toDateString(),
            'to' => $this->filled('to') ? $this->string('to')->toString() : $today->toDateString(),
            'actor' => $this->filled('actor') ? $this->integer('actor') : null,
            'log' => $this->filled('log') ? $this->string('log')->toString() : null,
            'action' => $this->filled('action') ? $this->string('action')->toString() : null,
            'subject' => $this->filled('subject') ? $this->string('subject')->toString() : null,
            'subject_id' => $this->filled('subject_id') ? $this->integer('subject_id') : null,
        ];
    }

    /**
     * The filter range as UTC instants, for querying `created_at`.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function range(): array
    {
        $filters = $this->filters();

        return [
            'start' => CarbonImmutable::parse($filters['from'], BusinessCalendar::TIMEZONE)->startOfDay()->utc(),
            'end' => CarbonImmutable::parse($filters['to'], BusinessCalendar::TIMEZONE)->addDay()->startOfDay()->utc(),
        ];
    }
}
