<?php

namespace App\Concerns;

use App\Exceptions\MonthClosed;
use App\Models\MonthClosure;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

trait StayDateRules
{
    use BusinessDateRules;

    /**
     * Rules for a stay's join or leave date (Y-m-d): not in the future, and
     * neither its month nor any later one closed, since the date decides who
     * shares every month after it. The stay actions check again, with the
     * member locked, along with the order of the stays.
     *
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function stayDateRules(): array
    {
        return [
            'required',
            ...$this->notInTheFutureRules(),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || ! strtotime($value)) {
                    return;
                }

                try {
                    MonthClosure::assertOpenFrom($value, $attribute);
                } catch (MonthClosed $closed) {
                    $fail($closed->getMessage());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function stayDateMessages(string $attribute): array
    {
        return [
            $attribute.'.before_or_equal' => __('The date cannot be in the future.'),
        ];
    }
}
