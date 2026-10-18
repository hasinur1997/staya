<?php

namespace App\Http\Requests\Ledger;

use App\Concerns\BusinessCalendar;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', LedgerEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::enum(LedgerEntryType::class)],
            'direction' => ['nullable', Rule::enum(LedgerDirection::class)],
        ];
    }

    /**
     * The filters, defaulting to the current month in Asia/Dhaka.
     *
     * @return array{from: string, to: string, type: LedgerEntryType|null, direction: LedgerDirection|null}
     */
    public function filters(): array
    {
        $today = BusinessCalendar::today();
        [$monthStart, $monthEnd] = BusinessCalendar::monthDates($today->year, $today->month);

        return [
            'from' => $this->filled('from') ? $this->string('from')->toString() : $monthStart,
            'to' => $this->filled('to') ? $this->string('to')->toString() : $monthEnd,
            'type' => $this->enum('type', LedgerEntryType::class),
            'direction' => $this->enum('direction', LedgerDirection::class),
        ];
    }
}
