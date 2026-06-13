<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use AIArmada\Events\Models\EventPass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventPass $pass,
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
        $pass = $this->pass->loadMissing(['event', 'occurrence', 'registration.participants']);
        $eventName = (string) config('events.notifications.ticket.event_name', 'AI Awakening');
        $brandName = (string) config('events.notifications.ticket.brand_name', 'Unfair Advantage');

        return (new MailMessage)
            ->from(
                config('events.notifications.ticket.from_address', 'info@unfairadvantage.my'),
                config('events.notifications.ticket.from_name', config('app.name')),
            )
            ->subject(sprintf('Your Ticket - %s : %s', $brandName, $eventName))
            ->markdown('events::notifications.ticket', [
                'pass' => $pass,
                'eventName' => $eventName,
                'brandName' => $brandName,
                'passNo' => $pass->pass_no,
            ]);
    }
}
