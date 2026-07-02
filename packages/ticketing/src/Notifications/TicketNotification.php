<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Notifications;

use AIArmada\Ticketing\Models\Pass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pass $pass,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Ticket')
            ->line('Thank you for your purchase.')
            ->line("Pass number: {$this->pass->pass_no}")
            ->line('Show this email or the QR code at the door.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'pass_id' => $this->pass->getKey(),
            'pass_no' => $this->pass->pass_no,
        ];
    }
}
