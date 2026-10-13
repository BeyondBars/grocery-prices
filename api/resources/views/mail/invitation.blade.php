@component('mail::message')
# Welcome to {{ config('app.name') }}

Hi {{ $name }},

An account has been created for you. Click the button below to choose your password and activate it.

@component('mail::button', ['url' => $url])
Set Your Password
@endcomponent

This link expires in {{ config('auth.passwords.users.expire') }} minutes. If it has expired, use "Forgot password" on the login page.
@endcomponent
