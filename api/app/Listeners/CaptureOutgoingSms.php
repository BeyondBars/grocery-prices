<?php

namespace App\Listeners;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;

/**
 * In local development, SMS notifications are written to the log instead of being sent through
 * Vonage (replaces codium/laravel-sms-capture, which doesn't support Laravel 12+).
 */
class CaptureOutgoingSms
{
    /**
     * Returning false stops the notification from being sent on this channel.
     */
    public function handle(NotificationSending $event): bool
    {
        if ($event->channel !== 'vonage' || !app()->environment('local')) {
            return true;
        }

        $recipient = $event->notifiable instanceof AnonymousNotifiable
            ? $event->notifiable->routeNotificationFor('vonage')
            : $event->notifiable->routeNotificationFor('vonage', $event->notification);

        $message = $event->notification->toVonage($event->notifiable);

        Log::info('SMS captured (not sent)', [
            'to' => $recipient,
            'message' => trim($message->content),
        ]);

        return false;
    }
}
