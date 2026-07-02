<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Notifications;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PassTransferredToOldHolderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pass $pass,
        public readonly PassHolder $newHolder,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Pass Has Been Transferred')
            ->line('Your pass has been transferred to someone else.')
            ->line("Pass number: {$this->pass->pass_no}")
            ->line('Transferred to: ' . ($this->newHolder->name ?? 'another person'))
            ->line('Reason: ' . ($this->reason ?? 'No reason given'))
            ->line('You no longer have access with this pass.');
    }
}
