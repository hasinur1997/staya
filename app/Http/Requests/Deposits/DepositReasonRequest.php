<?php

namespace App\Http\Requests\Deposits;

use App\Models\Deposit;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Rejecting or reversing a deposit: both need a reason. The route decides
 * which ability is checked.
 */
class DepositReasonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        /** @var Deposit $deposit */
        $deposit = $this->route('deposit');

        return Gate::inspect($this->routeIs('deposits.reverse') ? 'reverse' : 'reject', $deposit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'reason.required' => $this->routeIs('deposits.reverse')
                ? __('Give a reason for the reversal.')
                : __('Tell the member why their deposit was rejected.'),
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
