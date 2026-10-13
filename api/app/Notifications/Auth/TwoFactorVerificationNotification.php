<?php

namespace App\Notifications\Auth;

use App\Models\UserVerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class TwoFactorVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $method "email" or "sms".
     */
    public function __construct(
        protected UserVerificationCode $verificationCode,
        protected string $method,
    ) {
    }

    public function via($notifiable): array
    {
        return $this->method === 'sms' ? ['vonage'] : ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(email_subject('Your Verification Code'))
            ->markdown('mail.auth.two_factor_verification_code', [
                'verificationCode' => $this->verificationCode,
            ]);
    }

    public function toVonage($notifiable): VonageMessage
    {
        return (new VonageMessage)->content(sprintf(
            'Your %s verification code is %s. It expires in %d minutes.',
            config('app.name'),
            $this->verificationCode->code,
            UserVerificationCode::LIFETIME_MINUTES
        ));
    }
}
