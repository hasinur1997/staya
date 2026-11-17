<?php

namespace App\Http\Requests\Reports;

use App\Actions\Reports\MonthlyReport;
use App\Concerns\BusinessCalendar;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The report period: one month (`month`, default the current one) or a
 * custom range (`from` and `to`, at most a year), plus an optional member.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewOwn', MonthlyReport::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from', function (string $attribute, mixed $value, \Closure $fail): void {
                $from = $this->input('from');

                if (is_string($from) && is_string($value) && CarbonImmutable::parse($from)->addYear()->lt(CarbonImmutable::parse($value))) {
                    $fail(__('A custom range can cover at most one year.'));
                }
            }],
            'member' => ['nullable', 'integer', Rule::exists(Member::class, 'id')],
        ];
    }

    /**
     * The period to report: [from, to, month or null].
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    public function period(): array
    {
        if ($this->filled('from') && $this->filled('to')) {
            return [$this->string('from')->toString(), $this->string('to')->toString(), null];
        }

        $current = BusinessCalendar::today()->format('Y-m');
        $month = min($this->string('month')->toString() ?: $current, $current);
        [$first, $last] = BusinessCalendar::monthDates((int) substr($month, 0, 4), (int) substr($month, 5, 2));

        return [$first, $last, $month];
    }
}
