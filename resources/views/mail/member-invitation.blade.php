<x-mail::message>
# {{ __('You are invited to join :app', ['app' => config('app.name')]) }}

{{ __(':name invited you to join the mess. Open the link below and fill in your details to create your account.', ['name' => $inviterName]) }}

<x-mail::button :url="$url">
{{ __('Fill in your details') }}
</x-mail::button>

<x-mail::panel>
{{ __('The link works once and expires on :date (Asia/Dhaka).', ['date' => $expiresAt]) }}
</x-mail::panel>

<span class="muted">{{ __('If you were not expecting this, you can ignore this email.') }}</span>

<x-mail::subcopy>
{{ __('If the button does not work, copy this link into your browser:') }} [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>
