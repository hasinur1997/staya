<?php

namespace App\Concerns;

use App\Models\Member;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

trait MemberValidationRules
{
    use UploadValidationRules;

    /**
     * Rules for the member form, shared by self-registration and the staff
     * forms. Email and password belong to the user account and are validated
     * separately. A new member must upload an NID copy; an existing one keeps
     * theirs unless a new file is sent. On an existing member the NID fields
     * may only be sent by someone allowed to see them (`$canChangeNid`).
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function memberRules(?Member $member = null, bool $canChangeNid = true): array
    {
        $nid = match (true) {
            $member === null => ['required'],
            $canChangeNid => ['sometimes', 'required'],
            default => ['prohibited'],
        };

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'nid_number' => [...$nid, 'string', 'regex:/^(?:\d{10}|\d{13}|\d{17})$/', $this->uniqueNidRule($member)],
            'father_name' => ['required', 'string', 'max:150'],
            'mother_name' => ['required', 'string', 'max:150'],
            'permanent_address' => ['required', 'string', 'max:1000'],
            'nid_file' => match (true) {
                $member === null => ['required', ...$this->uploadRules(['pdf', 'jpg', 'jpeg', 'png'])],
                $canChangeNid => ['nullable', ...$this->uploadRules(['pdf', 'jpg', 'jpeg', 'png'])],
                default => ['prohibited'],
            },
            'photo' => ['nullable', ...$this->uploadRules(['jpg', 'jpeg', 'png', 'webp'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function memberMessages(): array
    {
        return [
            'phone.regex' => __('Enter a Bangladeshi mobile number, such as 01712345678.'),
            'nid_number.regex' => __('The NID number must have 10, 13 or 17 digits.'),
        ];
    }

    /**
     * Strip the spaces and dashes people type into phone and NID numbers.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function withoutNumberSeparators(array $input): array
    {
        foreach (['phone', 'nid_number'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = preg_replace('/[\s-]+/', '', $input[$field]);
            }
        }

        return $input;
    }

    /**
     * The member's own columns from validated input, trimmed, with the phone
     * number normalized. Fields that were not sent (the NID number on an
     * edit) are left out.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    protected function memberDetails(array $validated): array
    {
        $details = [];

        foreach (['first_name', 'last_name', 'phone', 'nid_number', 'father_name', 'mother_name', 'permanent_address'] as $field) {
            if (array_key_exists($field, $validated)) {
                $details[$field] = trim((string) $validated[$field]);
            }
        }

        if (isset($details['phone'])) {
            $details['phone'] = $this->normalizedPhone($details['phone']);
        }

        return $details;
    }

    /**
     * A valid Bangladeshi mobile number in `+8801XXXXXXXXX` form.
     */
    protected function normalizedPhone(string $phone): string
    {
        return '+880'.substr(preg_replace('/\D/', '', $phone) ?? '', -10);
    }

    /**
     * NID numbers are encrypted, so uniqueness is checked on their hash.
     */
    private function uniqueNidRule(?Member $member): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($member): void {
            if (! is_string($value)) {
                return;
            }

            $taken = Member::query()
                ->where('nid_number_hash', Member::nidHash($value))
                ->when($member, fn ($query) => $query->whereKeyNot($member->id))
                ->exists();

            if ($taken) {
                $fail(__('This NID number is already registered.'));
            }
        };
    }
}
