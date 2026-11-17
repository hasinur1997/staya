<?php

namespace App\Http\Requests\Reports;

use App\Actions\Reports\MonthlyReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReopenMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('closeMonth', MonthlyReport::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => __('Say why the month is being reopened.')];
    }

    public function month(): string
    {
        return $this->string('month')->toString();
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
