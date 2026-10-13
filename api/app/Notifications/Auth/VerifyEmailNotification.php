<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable,
        InteractsWithTime;

    /**
     * Constructor
     *
     * @param protectedarray $protectedparameters
     */
    public function __construct(
        protected array $parameters
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
            ->markdown('mail.email_verification', [
                'url' => $this->verificationUrl($notifiable) . '&identifier=' . base64_encode($notifiable->routeNotificationFor('mail'))
            ]);
    }

    /**
     * Get the verification URL.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::appSigned(
            "/verification",
            $this->parameters,
            now()->addMinutes(1440)
        );
    }
}
