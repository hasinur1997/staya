<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Member;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class UpdateMemberRequest extends FormRequest
{
    use MemberValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request. Changing the
     * login email needs the right to edit the account as well.
     */
    public function authorize(): Response
    {
        $member = $this->member();
        $update = Gate::inspect('update', $member);

        if ($update->denied() || $this->string('email')->toString() === $member->user->email) {
            return $update;
        }

        return Gate::inspect('changeEmail', $member);
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
        return [
            ...$this->memberRules($this->member(), canChangeNid: Gate::allows('viewNid', $this->member())),
            'email' => $this->emailRules($this->member()->user_id),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
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

    public function nidFile(): ?UploadedFile
    {
        $file = $this->file('nid_file');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    private function member(): Member
    {
        /** @var Member */
        return $this->route('member');
    }
}
