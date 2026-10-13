@component('mail::message')
# Reset Password

Hi {{ $name }},

We received a request to reset the password for your {{ config('app.name') }} account. Click the button below to set a new password.

@component('mail::button', ['url' => $url])
Reset Password
@endcomponent

If you did not request a password reset, you can ignore this email. Your account remains secure.
@endcomponent
