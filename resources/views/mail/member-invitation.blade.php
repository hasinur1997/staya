<x-mail::message>
# {{ __('You are invited to join :app', ['app' => config('app.name')]) }}

{{ __(':name invited you to join the mess. Open the link below and fill in your details to create your account.', ['name' => $inviterName]) }}

<x-mail::button :url="$url">
{{ __('Fill in your details') }}
</x-mail::button>

{{ __('The link works once and expires on :date (Asia/Dhaka).', ['date' => $expiresAt]) }}

{{ __('If you were not expecting this, you can ignore this email.') }}
</x-mail::message>
