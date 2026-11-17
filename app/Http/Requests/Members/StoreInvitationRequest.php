<?php

namespace App\Http\Requests\Members;

use App\Actions\Members\InviteMember;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', MemberInvitation::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'expires_in_days' => ['nullable', 'integer', 'between:1,30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.unique' => __('Someone with this email already has an account.')];
    }

    public function expiresInDays(): int
    {
        return $this->filled('expires_in_days') ? $this->integer('expires_in_days') : InviteMember::DEFAULT_DAYS;
    }
}
