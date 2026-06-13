<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use AIArmada\Events\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventRegistration $registration,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eventName = (string) config('events.notifications.welcome.event_name', 'AI Awakening');
        $brandName = (string) config('events.notifications.welcome.brand_name', 'Unfair Advantage');

        return (new MailMessage)
            ->from(
                config('events.notifications.welcome.from_address', 'info@unfairadvantage.my'),
                config('events.notifications.welcome.from_name', config('app.name')),
            )
            ->subject(sprintf("You're In - %s : %s Registration Confirmed", $brandName, $eventName))
            ->markdown('events::notifications.welcome', [
                'registration' => $this->registration,
                'eventName' => $eventName,
                'brandName' => $brandName,
            ]);
    }
}
