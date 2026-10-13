@component('mail::message')
# Verify Your Email

Click the button below to verify this email address for your {{ config('app.name') }} account.

@component('mail::button', ['url' => $url])
Verify Email
@endcomponent

If you didn't request this, you can ignore this email.
@endcomponent
