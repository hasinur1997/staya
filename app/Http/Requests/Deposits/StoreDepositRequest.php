<?php

namespace App\Http\Requests\Deposits;

use App\Concerns\BusinessDateRules;
use App\Concerns\MoneyRules;
use App\Enums\DepositMethod;
use App\Models\Deposit;
use App\Models\Member;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDepositRequest extends FormRequest
{
    use BusinessDateRules, MoneyRules;

    /**
     * Determine if the user is authorized to make this request. Without a
     * `member_id` the deposit is the user's own.
     */
    public function authorize(): Response
    {
        $member = $this->member();

        if ($member === null) {
            // An unknown member_id fails validation instead.
            return $this->filled('member_id') ? Response::allow() : Gate::inspect('create', Deposit::class);
        }

        $create = Gate::inspect('createFor', [Deposit::class, $member]);

        if ($create->denied() || ! $this->boolean('approve')) {
            return $create;
        }

        return Gate::inspect('approveOnSave', [Deposit::class, $member]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['sometimes', 'required', 'integer', Rule::exists(Member::class, 'id')],
            'amount' => $this->moneyRules(positive: true),
            'deposit_date' => [...$this->businessDateRules(), $this->memberActiveInMonthRule()],
            'method' => ['sometimes', Rule::enum(DepositMethod::class)],
            'approve' => ['sometimes', 'boolean'],
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
            ...$this->businessDateMessages('deposit_date'),
            ...$this->moneyMessages('amount', positive: true),
        ];
    }

    /**
     * The member the deposit is for: the one chosen, or the user's own.
     */
    public function member(): ?Member
    {
        if ($this->filled('member_id')) {
            return Member::query()->find($this->integer('member_id'));
        }

        return $this->user()?->member;
    }

    /**
     * Deposits count in the month of their date, so the member must have
     * been living in the mess that month.
     */
    private function memberActiveInMonthRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $member = $this->member();

            if ($member === null || ! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return;
            }

            if (! $member->isActiveInMonthOf($value)) {
                $fail(__(':name was not living in the mess in that month.', ['name' => $member->full_name]));
            }
        };
    }
}
