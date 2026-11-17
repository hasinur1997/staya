<?php

namespace App\Http\Requests\Meals;

use App\Models\Meal;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecordMealsRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('record', Meal::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array'],
            'entries.*' => ['array'],
            'entries.*.*' => ['required', 'integer', 'min:0', 'max:'.Meal::MAX_PER_TYPE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['entries.*.*.max' => __('A member can have at most :max meals of one type in a day.', ['max' => Meal::MAX_PER_TYPE])];
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ((array) $this->validated('entries') as $memberId => $counts) {
            foreach ((array) $counts as $typeId => $count) {
                $entries[(int) $memberId][(int) $typeId] = (int) $count;
            }
        }

        return $entries;
    }
}
