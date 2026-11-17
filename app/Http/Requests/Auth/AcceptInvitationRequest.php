<?php

namespace App\Http\Requests\Auth;

use App\Concerns\MemberValidationRules;
use App\Concerns\PasswordValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The invited person fills in the same fields as a sign-up, except the
 * email, which comes from the invitation. The link itself is the authority,
 * so there is nothing to authorize here; the controller checks its state.
 */
class AcceptInvitationRequest extends FormRequest
{
    use MemberValidationRules, PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->withoutNumberSeparators($this->only(['phone', 'nid_number'])));
    }

    /**
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->memberRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->memberMessages();
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return $this->memberDetails($this->validated());
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
}
