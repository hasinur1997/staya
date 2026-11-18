<?php

namespace App\Http\Requests\Settings;

use App\Models\MailSetting;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMailSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('manage', MailSetting::current());
    }

    /**
     * Get the validation rules that apply to the request. Host is required for SMTP
     * and the sender for SMTP and sendmail.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mailer' => ['required', Rule::in(MailSetting::mailers())],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in([MailSetting::SSL, MailSetting::TLS])],
            'username' => ['nullable', 'string', 'max:255'],
            // Left blank, the saved password is kept.
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required_if:mailer,smtp,sendmail', 'nullable', 'email:rfc', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * The validated settings. `password` is absent unless a new one was typed.
     *
     * @return array{mailer: string, host: string|null, port: int, encryption: string, username: string|null, from_address: string|null, from_name: string|null, password?: string}
     */
    public function settings(): array
    {
        $values = [
            'mailer' => $this->string('mailer')->toString(),
            'host' => $this->filled('host') ? $this->string('host')->trim()->toString() : null,
            'port' => $this->integer('port'),
            'encryption' => $this->string('encryption')->toString(),
            'username' => $this->filled('username') ? $this->string('username')->trim()->toString() : null,
            'from_address' => $this->filled('from_address') ? $this->string('from_address')->trim()->toString() : null,
            'from_name' => $this->filled('from_name') ? $this->string('from_name')->trim()->toString() : null,
        ];

        if ($this->filled('password')) {
            $values['password'] = $this->string('password')->toString();
        }

        return $values;
    }
}
