@component('mail::message')
# Your Verification Code

Use the code below to finish signing in. It expires in {{ \App\Models\UserVerificationCode::LIFETIME_MINUTES }} minutes.

@component('mail::panel')
{{ $verificationCode->formatted_code }}
@endcomponent

If you didn't try to sign in, change your password straight away.
@endcomponent
