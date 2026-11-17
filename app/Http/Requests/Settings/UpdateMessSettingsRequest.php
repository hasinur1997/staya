<?php

namespace App\Http\Requests\Settings;

use App\Concerns\BusinessDateRules;
use App\Concerns\MoneyRules;
use App\Enums\CostSplit;
use App\Models\MessSetting;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMessSettingsRequest extends FormRequest
{
    use BusinessDateRules, MoneyRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', MessSetting::current());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mess_name' => ['nullable', 'string', 'max:150'],
            'mess_address' => ['nullable', 'string', 'max:1000'],
            'opening_balance' => $this->moneyRules(),
            // The opening-balance ledger entry is dated at the fund start.
            'fund_start_date' => ['nullable', 'required_unless:opening_balance,0,0.0,0.00', ...$this->notInTheFutureRules()],
            'grocery_edit_window_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'members_can_view_balance' => ['required', 'boolean'],
            'max_upload_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'cost_split' => ['required', Rule::enum(CostSplit::class)],
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
            ...$this->businessDateMessages('fund_start_date'),
            ...$this->moneyMessages('opening_balance'),
            'fund_start_date.required_unless' => __('Set the fund start date to record an opening balance.'),
        ];
    }

    /**
     * The validated settings, with the opening balance at scale 2.
     *
     * @return array{mess_name: string|null, mess_address: string|null, opening_balance: string, fund_start_date: string|null, grocery_edit_window_minutes: int, members_can_view_balance: bool, max_upload_kb: int, cost_split: string}
     */
    public function settings(): array
    {
        /** @var numeric-string $openingBalance Checked by the regex rule. */
        $openingBalance = $this->string('opening_balance')->toString();

        return [
            'mess_name' => $this->filled('mess_name') ? $this->string('mess_name')->trim()->toString() : null,
            'mess_address' => $this->filled('mess_address') ? $this->string('mess_address')->trim()->toString() : null,
            'opening_balance' => bcadd($openingBalance, '0', 2),
            'fund_start_date' => $this->filled('fund_start_date') ? $this->string('fund_start_date')->toString() : null,
            'grocery_edit_window_minutes' => $this->integer('grocery_edit_window_minutes'),
            'members_can_view_balance' => $this->boolean('members_can_view_balance'),
            'max_upload_kb' => $this->integer('max_upload_kb'),
            'cost_split' => $this->string('cost_split')->toString(),
        ];
    }
}
