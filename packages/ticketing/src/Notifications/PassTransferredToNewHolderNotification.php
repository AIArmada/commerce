<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Notifications;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PassTransferredToNewHolderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pass $pass,
        public readonly PassHolder $previousHolder,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A Pass Has Been Transferred to You')
            ->line("You've been transferred a pass.")
            ->line("Pass number: {$this->pass->pass_no}")
            ->line('Transferred to you by: ' . ($this->previousHolder->name ?? 'previous holder'))
            ->line('Reason: ' . ($this->reason ?? 'No reason given'))
            ->line('Show this email or the QR code at the door.');
    }
}
