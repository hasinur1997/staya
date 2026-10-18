<?php

namespace App\Http\Requests\Settings;

use App\Concerns\BusinessDateRules;
use App\Models\MessSetting;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMessSettingsRequest extends FormRequest
{
    use BusinessDateRules;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mess_name' => ['nullable', 'string', 'max:150'],
            'mess_address' => ['nullable', 'string', 'max:1000'],
            // A plain decimal string that fits DECIMAL(12,2); never parsed as a float.
            'opening_balance' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'fund_start_date' => ['nullable', ...$this->notInTheFutureRules()],
            'grocery_edit_window_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'members_can_view_balance' => ['required', 'boolean'],
            'max_upload_kb' => ['required', 'integer', 'min:100', 'max:20480'],
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
            'opening_balance.regex' => __('Enter an amount of zero or more with at most two decimal places.'),
        ];
    }

    /**
     * The validated settings, with the opening balance at scale 2.
     *
     * @return array{mess_name: string|null, mess_address: string|null, opening_balance: string, fund_start_date: string|null, grocery_edit_window_minutes: int, members_can_view_balance: bool, max_upload_kb: int}
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
        ];
    }
}
