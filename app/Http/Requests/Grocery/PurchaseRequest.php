<?php

namespace App\Http\Requests\Grocery;

use App\Actions\Grocery\CalculatePurchaseTotals;
use App\Concerns\BusinessDateRules;
use App\Concerns\MoneyRules;
use App\Concerns\UploadValidationRules;
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
     * @return array{purchase_date: string, notes: string|null, items: array<int, ItemInput>}
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
        ];
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
