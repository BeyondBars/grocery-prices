<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $token Password broker token.
     * @param bool $invite Whether this is an invitation for a new user (set first password).
     */
    public function __construct(
        public string $token,
        public bool $invite = false,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(email_subject($this->invite ? 'You have been invited' : 'Reset Password'))
            ->markdown($this->invite ? 'mail.invitation' : 'mail.reset_password', [
                'name' => $notifiable->name,
                'url' => url()->app(sprintf('/set-password/%s?email=%s', $this->token, rawurlencode($notifiable->email))),
            ]);
    }
}
