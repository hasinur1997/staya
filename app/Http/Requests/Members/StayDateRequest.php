<?php

namespace App\Http\Requests\Members;

use App\Concerns\StayDateRules;
use App\Models\Member;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A stay change on one member: deactivate (`left_on`), reactivate or
 * approve a registration (`joined_on`). The route decides which.
 */
class StayDateRequest extends FormRequest
{
    use StayDateRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect($this->ability(), $this->member());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [$this->field() => $this->stayDateRules()];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->stayDateMessages($this->field());
    }

    /**
     * The validated Y-m-d date.
     */
    public function stayDate(): string
    {
        return $this->string($this->field())->toString();
    }

    private function field(): string
    {
        return $this->ability() === 'deactivate' ? 'left_on' : 'joined_on';
    }

    private function ability(): string
    {
        return match (true) {
            $this->routeIs('members.deactivate') => 'deactivate',
            $this->routeIs('members.reactivate') => 'reactivate',
            default => 'reviewRegistration',
        };
    }

    private function member(): Member
    {
        /** @var Member */
        return $this->route('member');
    }
}
