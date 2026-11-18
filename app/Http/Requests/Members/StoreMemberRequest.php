<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\StayDateRules;
use App\Models\Member;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    use MemberValidationRules, PasswordValidationRules, ProfileValidationRules, StayDateRules;

    /**
     * Determine if the user is authorized to make this request. Linking an
     * existing account is checked by `MemberPolicy::linkAccount`.
     */
    public function authorize(): Response
    {
        $create = Gate::inspect('create', Member::class);

        if ($create->denied() || ! $this->linksExistingUser()) {
            return $create;
        }

        $user = User::query()->find($this->integer('user_id'));

        if ($user === null) {
            return $create;
        }

        return Gate::inspect('linkAccount', [Member::class, $user]);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->withoutNumberSeparators($this->only(['phone', 'nid_number'])));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        $newAccount = ! $this->linksExistingUser();

        return [
            ...$this->memberRules(),
            'account' => ['required', Rule::in(['new', 'existing'])],
            'email' => $newAccount ? $this->emailRules() : ['exclude'],
            'password' => $newAccount ? $this->passwordRules() : ['exclude'],
            'user_id' => $newAccount ? ['exclude'] : [
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::unique(Member::class, 'user_id'),
            ],
            'joined_on' => $this->stayDateRules(),
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
            ...$this->memberMessages(),
            ...$this->stayDateMessages('joined_on'),
            'user_id.unique' => __('This user already has a member profile.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return $this->memberDetails($this->validated());
    }

    /**
     * @return array{user_id: int}|array{email: string, password: string}
     */
    public function account(): array
    {
        return $this->linksExistingUser()
            ? ['user_id' => $this->integer('user_id')]
            : ['email' => $this->string('email')->toString(), 'password' => $this->string('password')->toString()];
    }

    public function nidFile(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('nid_file');
    }

    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    private function linksExistingUser(): bool
    {
        return $this->input('account') === 'existing';
    }
}
