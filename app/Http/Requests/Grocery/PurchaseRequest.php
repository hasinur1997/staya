<?php

namespace App\Http\Requests\Grocery;

use App\Actions\Grocery\CalculatePurchaseTotals;
use App\Actions\Grocery\RecordPurchase;
use App\Concerns\BusinessDateRules;
use App\Concerns\MoneyRules;
use App\Concerns\UploadValidationRules;
use App\Enums\GroceryPaidBy;
use App\Enums\GroceryUnit;
use App\Enums\MemberStatus;
use App\Models\GroceryPurchase;
use App\Models\Member;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Recording or editing a grocery purchase. Totals are never accepted from
 * the client: only the items are validated, and the server prices them.
 *
 * @phpstan-import-type ItemInput from CalculatePurchaseTotals
 * @phpstan-import-type PurchaseInput from RecordPurchase
 */
class PurchaseRequest extends FormRequest
{
    use BusinessDateRules, MoneyRules, UploadValidationRules;

    /**
     * Determine if the user is authorized to make this request. Without a
     * `buyer_member_id` a new purchase is the user's own.
     */
    public function authorize(): Response
    {
        $purchase = $this->purchase();

        if ($purchase !== null) {
            return Gate::inspect('update', $purchase);
        }

        $buyer = $this->buyer();

        if ($buyer !== null) {
            return Gate::inspect('createFor', [GroceryPurchase::class, $buyer]);
        }

        // A missing or unknown buyer fails validation instead.
        return $this->filled('buyer_member_id') || Gate::allows('createForOthers', GroceryPurchase::class)
            ? Response::allow()
            : Gate::inspect('create', GroceryPurchase::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        $buyerRequired = $this->purchase() === null && ! ($this->user()?->isActiveMember() ?? false);

        return [
            'purchase_date' => $this->businessDateRules(),
            'buyer_member_id' => [
                $buyerRequired ? 'required' : 'sometimes',
                'required',
                'integer',
                Rule::exists(Member::class, 'id'),
                $this->activeBuyerRule(),
            ],
            'paid_by' => ['sometimes', 'required', Rule::enum(GroceryPaidBy::class)],
            'paid_by_member_id' => [
                Rule::requiredIf($this->input('paid_by') === GroceryPaidBy::Member->value && $this->canChoosePayer()),
                'nullable',
                'integer',
                Rule::exists(Member::class, 'id'),
                $this->activePayerRule(),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', ...$this->uploadRules(['jpg', 'jpeg', 'png', 'webp', 'pdf'])],
            'remove_receipt' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_name' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,3})?$/', $this->positiveQuantityRule()],
            'items.*.unit' => ['required', Rule::enum(GroceryUnit::class)],
            'items.*.unit_price' => $this->moneyRules(positive: true),
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
            ...$this->businessDateMessages('purchase_date'),
            ...$this->moneyMessages('items.*.unit_price', positive: true),
            'buyer_member_id.required' => __('Choose who made the purchase.'),
            'paid_by_member_id.required' => __('Choose the member who paid.'),
            'items.required' => __('Add at least one item.'),
            'items.min' => __('Add at least one item.'),
            'items.*.item_name.required' => __('Name the item.'),
            'items.*.quantity.required' => __('Enter a quantity.'),
            'items.*.quantity.regex' => __('Enter a quantity greater than zero with at most three decimal places.'),
            'items.*.unit.required' => __('Choose a unit.'),
            'items.*.unit_price.required' => __('Enter a unit price.'),
        ];
    }

    /**
     * The purchase being edited; null when recording a new one.
     */
    public function purchase(): ?GroceryPurchase
    {
        $purchase = $this->route('purchase');

        return $purchase instanceof GroceryPurchase ? $purchase : null;
    }

    /**
     * The buyer: the member chosen, or for a new purchase the user's own
     * profile.
     */
    public function buyer(): ?Member
    {
        if ($this->filled('buyer_member_id')) {
            return Member::query()->find($this->integer('buyer_member_id'));
        }

        return $this->purchase() === null ? $this->user()?->member : null;
    }

    /**
     * The validated purchase without anything the server computes.
     *
     * @return PurchaseInput
     */
    public function purchaseData(): array
    {
        /** @var array<int, array<string, string>> $items */
        $items = $this->validated('items');

        return [
            'purchase_date' => $this->string('purchase_date')->toString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'items' => array_map(fn (array $item): array => [
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
            ], array_values($items)),
            'paid_by' => $this->paidBy(),
            'payer' => $this->paidBy() === GroceryPaidBy::Member ? $this->payer() : null,
        ];
    }

    /**
     * Who paid: what the form says, else what the purchase already has,
     * else the fund.
     */
    public function paidBy(): GroceryPaidBy
    {
        return $this->enum('paid_by', GroceryPaidBy::class)
            ?? $this->purchase()->paid_by
            ?? GroceryPaidBy::Fund;
    }

    /**
     * The member who paid from their own pocket: the one chosen, else the
     * one the purchase already has.
     */
    public function payer(): ?Member
    {
        if ($this->filled('paid_by_member_id')) {
            return Member::query()->find($this->integer('paid_by_member_id'));
        }

        $existing = $this->purchase()?->payer;

        if ($existing !== null || ! $this->has('paid_by') || $this->canChoosePayer()) {
            return $existing;
        }

        // Without the right to choose, "paid by member" means oneself.
        return $this->user()?->member;
    }

    /**
     * Only staff who may record for others can name another member as
     * the one who paid; everyone else can only name themselves.
     */
    private function canChoosePayer(): bool
    {
        return Gate::allows('createForOthers', GroceryPurchase::class);
    }

    /**
     * A payer who is newly chosen must be an active member; the one a
     * purchase already has may stay after they leave.
     */
    private function activePayerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $payer = $this->payer();

            if ($payer === null || $payer->id === $this->purchase()?->paid_by_member_id) {
                return;
            }

            if (! $this->canChoosePayer() && $payer->id !== $this->user()?->member?->id) {
                $fail(__('You can only record yourself as having paid.'));

                return;
            }

            if ($payer->status !== MemberStatus::Active) {
                $fail(__('Only an active member can be recorded as having paid.'));
            }
        };
    }

    public function receipt(): ?UploadedFile
    {
        $receipt = $this->file('receipt');

        return $receipt instanceof UploadedFile ? $receipt : null;
    }

    /**
     * A buyer who is newly chosen must be an active member; the buyer a
     * purchase already has may stay after they leave.
     */
    private function activeBuyerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $buyer = $this->buyer();

            if ($buyer === null || $buyer->id === $this->purchase()?->buyer_member_id) {
                return;
            }

            if ($buyer->status !== MemberStatus::Active) {
                $fail(__('Only an active member can be the buyer.'));
            }
        };
    }

    private function positiveQuantityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && is_numeric($value) && preg_match('/^\d{1,7}(\.\d{1,3})?$/', $value) === 1 && bccomp($value, '0', 3) <= 0) {
                $fail(__('Enter a quantity greater than zero with at most three decimal places.'));
            }
        };
    }
}
