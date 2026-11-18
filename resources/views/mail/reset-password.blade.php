<x-mail::message>
# {{ __('Reset your password') }}

{{ __('We received a request to reset the password for your :app account. Use the button below to choose a new one.', ['app' => config('app.name')]) }}

<x-mail::button :url="$url">
{{ __('Reset password') }}
</x-mail::button>

<x-mail::panel>
{{ __('This link expires in :count minutes.', ['count' => $expireMinutes]) }}
</x-mail::panel>

<span class="muted">{{ __('If you did not request a password reset, no action is needed. Your password stays the same.') }}</span>

<x-mail::subcopy>
{{ __('If the button does not work, copy this link into your browser:') }} [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>
