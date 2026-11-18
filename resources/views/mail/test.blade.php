<x-mail::message>
# {{ __('Email is working') }}

{{ __('If you can read this, :app can send email from this server.', ['app' => config('app.name')]) }}
</x-mail::message>
