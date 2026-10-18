<?php

namespace App\Http\Requests\Ledger;

use App\Concerns\BusinessDateRules;
use App\Concerns\MoneyRules;
use App\Enums\LedgerDirection;
use App\Models\LedgerEntry;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
{
    use BusinessDateRules, MoneyRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('adjust', LedgerEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(LedgerDirection::class)],
            'amount' => $this->moneyRules(positive: true),
            'entry_date' => $this->businessDateRules(),
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->businessDateMessages('entry_date'),
            ...$this->moneyMessages('amount', positive: true),
            'reason.required' => __('Give a reason for the adjustment.'),
        ];
    }

    /**
     * The validated adjustment, with the amount at scale 2.
     *
     * @return array{direction: LedgerDirection, amount: string, entry_date: string, reason: string}
     */
    public function adjustment(): array
    {
        /** @var numeric-string $amount Checked by the regex rule. */
        $amount = $this->string('amount')->toString();

        return [
            'direction' => $this->enum('direction', LedgerDirection::class) ?? LedgerDirection::Credit,
            'amount' => bcadd($amount, '0', 2),
            'entry_date' => $this->string('entry_date')->toString(),
            'reason' => $this->string('reason')->trim()->toString(),
        ];
    }
}
