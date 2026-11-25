<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use AIArmada\Events\Data\EventChangeNoticeNotificationData;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventChangeNoticeNotification extends Notification
{
    public function __construct(
        public readonly EventChangeNoticeNotificationData $data,
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
            ->subject($this->data->title)
            ->line($this->data->message ?: $this->data->title);
    }
}
