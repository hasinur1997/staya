<?php

namespace App\Http\Requests\Reports;

use App\Actions\Reports\MonthlyReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CloseMonthRequest extends FormRequest
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
        return ['month' => ['required', 'date_format:Y-m']];
    }

    public function month(): string
    {
        return $this->string('month')->toString();
    }
}
