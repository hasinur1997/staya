<?php

namespace App\Http\Requests\Settings;

use App\Actions\Settings\ResetMessData;
use App\Models\MessSetting;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResetMessDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('resetData', MessSetting::current());
    }

    /**
     * Erasing is irreversible, so it needs the exact phrase and the
     * requester's own password on top of the permission.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'in:'.ResetMessData::CONFIRMATION_PHRASE],
            'password' => ['required', 'string', 'current_password'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
