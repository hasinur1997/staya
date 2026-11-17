<?php

namespace App\Http\Requests\Grocery;

use App\Models\GroceryPurchase;
use App\Policies\GroceryPurchasePolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Voiding a purchase. Staff must say why; the owner inside the edit window
 * need not.
 */
class VoidPurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->purchase());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $needsReason = $this->user() === null
            || app(GroceryPurchasePolicy::class)->voidNeedsReason($this->user(), $this->purchase());

        return [
            'reason' => [$needsReason ? 'required' : 'nullable', 'string', 'max:1000'],
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
            'reason.required' => __('Give a reason for voiding this purchase.'),
        ];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    private function purchase(): GroceryPurchase
    {
        /** @var GroceryPurchase */
        return $this->route('purchase');
    }
}
