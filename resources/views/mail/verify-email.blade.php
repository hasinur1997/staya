<x-mail::message>
# {{ __('Verify your email address') }}

{{ __('Welcome to :app. Confirm that this is your email address to finish setting up your account.', ['app' => config('app.name')]) }}

<x-mail::button :url="$url">
{{ __('Verify email address') }}
</x-mail::button>

<span class="muted">{{ __('If you did not create an account, you can ignore this email.') }}</span>

<x-mail::subcopy>
{{ __('If the button does not work, copy this link into your browser:') }} [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>
