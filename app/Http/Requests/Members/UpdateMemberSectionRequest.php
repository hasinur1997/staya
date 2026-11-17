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

/**
 * Edits one card of the member profile: pictures, personal details, family
 * details or the NID. Only that section's fields are accepted.
 */
class UpdateMemberSectionRequest extends FormRequest
{
    use MemberValidationRules, ProfileValidationRules;

    /**
     * Whoever may update the member may edit a section; the email needs the
     * right to change the account too, and the NID needs the right to see it.
     */
    public function authorize(): Response
    {
        $member = $this->member();
        $update = Gate::inspect('update', $member);

        if ($update->denied()) {
            return $update;
        }

        return match ($this->section()) {
            'nid' => Gate::inspect('viewNid', $member),
            'personal' => $this->string('email')->toString() === $member->user->email
                ? $update
                : Gate::inspect('changeEmail', $member),
            default => $update,
        };
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
        $member = $this->member();
        $all = [
            ...$this->memberRules($member, canChangeNid: true),
            'email' => $this->emailRules($member->user_id),
            'banner' => ['nullable', ...$this->uploadRules(['jpg', 'jpeg', 'png', 'webp'])],
            'remove_banner' => ['sometimes', 'boolean'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];

        $fields = match ($this->section()) {
            'images' => ['photo', 'banner', 'remove_banner', 'remove_photo'],
            'personal' => ['first_name', 'last_name', 'name_bn', 'phone', 'email'],
            'family' => ['father_name', 'father_name_bn', 'mother_name', 'mother_name_bn', 'permanent_address'],
            default => ['nid_number', 'nid_file'],
        };

        return array_intersect_key($all, array_flip($fields));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->memberMessages();
    }

    /**
     * The member columns this section changes.
     *
     * @return array<string, string>
     */
    public function details(): array
    {
        return $this->memberDetails($this->validated());
    }

    public function email(): string
    {
        $email = $this->validated('email');

        return is_string($email) ? $email : $this->member()->user->email;
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

    public function banner(): ?UploadedFile
    {
        $banner = $this->file('banner');

        return $banner instanceof UploadedFile ? $banner : null;
    }

    public function removesBanner(): bool
    {
        return $this->boolean('remove_banner');
    }

    public function removesPhoto(): bool
    {
        return $this->boolean('remove_photo');
    }

    private function section(): string
    {
        return (string) $this->route('section');
    }

    private function member(): Member
    {
        /** @var Member */
        return $this->route('member');
    }
}
