<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProgramJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $programName,
        private readonly bool $approved,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->approved
                ? "You're in: {$this->programName}"
                : "Application received: {$this->programName}")
            ->line($this->approved
                ? "Your application to {$this->programName} was approved. Your tracking links are ready."
                : "Your application to {$this->programName} is pending review. We'll notify you once approved.");
    }
}
