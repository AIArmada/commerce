<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class EngagementReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public static function getNotificationClass(): string
    {
        return config('engagement.notifications.reminder', self::class);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('engagement.reminder.default_channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): mixed
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['notification' => 'reminder'];
    }
}
