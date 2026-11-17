<?php

namespace App\Http\Requests\Grocery;

use App\Concerns\MoneyRules;
use App\Models\BuyerPayable;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReimbursePayableRequest extends FormRequest
{
    use MoneyRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        /** @var BuyerPayable $payable */
        $payable = $this->route('payable');

        return Gate::inspect('reimburse', $payable);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => $this->moneyRules(positive: true),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->moneyMessages('amount', positive: true);
    }
}
