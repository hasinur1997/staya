<?php

namespace App\Actions\Fortify;

use App\Actions\Members\RegisterApplicant;
use App\Concerns\MemberValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use MemberValidationRules, PasswordValidationRules, ProfileValidationRules;

    public function __construct(private RegisterApplicant $registerApplicant) {}

    /**
     * Validate a public sign-up (the full member form plus a password) and
     * create the account with a Pending member. It has no access until the
     * registration is approved.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input = $this->withoutNumberSeparators($input);

        $validated = Validator::make($input, [
            ...$this->memberRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ], $this->memberMessages())->validate();

        /** @var UploadedFile $nidFile */
        $nidFile = $validated['nid_file'];
        $photo = $validated['photo'] ?? null;

        return $this->registerApplicant->handle(
            $this->memberDetails($validated),
            (string) $validated['email'],
            (string) $validated['password'],
            $nidFile,
            $photo instanceof UploadedFile ? $photo : null,
        );
    }
}
