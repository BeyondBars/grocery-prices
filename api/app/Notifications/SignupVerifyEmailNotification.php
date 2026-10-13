<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SignupVerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable,
        InteractsWithTime;

    /**
     * Constructor
     *
     * @param protectedarray $protectedparameters
     */
    public function __construct(
        protected string $url
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verify Your Email')
            ->markdown('mail.email_verification', ['url' => $this->url ]);
    }
}
