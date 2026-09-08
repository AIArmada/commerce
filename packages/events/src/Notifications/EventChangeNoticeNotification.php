<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventChangeNoticeNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message ?: $this->title);
    }
}
