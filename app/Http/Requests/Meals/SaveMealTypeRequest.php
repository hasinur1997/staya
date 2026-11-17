<?php

namespace App\Http\Requests\Meals;

use App\Models\MealType;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveMealTypeRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('manage', MealType::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('mealType');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(MealType::class)->ignore($type instanceof MealType ? $type->id : null)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, sort_order: int, is_active: bool}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'sort_order' => $this->integer('sort_order'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
