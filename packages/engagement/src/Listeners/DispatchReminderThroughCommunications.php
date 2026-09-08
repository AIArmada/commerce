<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Listeners;

use AIArmada\Communications\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Engagement\Events\ReminderDue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

final class DispatchReminderThroughCommunications
{
    public function __construct(
        private readonly DispatchManagedNotificationAction $dispatcher,
        private readonly AttachCommunicationReferenceAction $references,
    ) {}

    public function handle(ReminderDue $event): void
    {
        $reminder = $event->reminder;
        $recipient = $reminder->recipient;

        if (! $recipient instanceof Model) {
            throw new InvalidArgumentException('Reminder recipients must resolve to an Eloquent model.');
        }

        $notificationClass = $reminder->notification_class
            ?? config('engagement.notifications.reminder');

        if (! is_string($notificationClass) || ! is_a($notificationClass, Notification::class, true)) {
            throw new InvalidArgumentException('The configured reminder notification must extend Laravel Notification.');
        }

        $notification = app($notificationClass);

        if (! $notification instanceof Notification) {
            throw new InvalidArgumentException('The configured reminder notification must resolve to a Laravel Notification.');
        }

        $communication = $this->dispatcher->handle(
            $recipient,
            $notification,
            new CommunicationContextData(
                purpose: 'engagement.reminder',
                subjectType: $reminder->remindable_type,
                subjectId: (string) $reminder->remindable_id,
                idempotencyKey: 'engagement-reminder:' . $reminder->getKey(),
                metadata: [
                    'reminder_id' => (string) $reminder->getKey(),
                    'reminder_type' => $reminder->reminder_type,
                    'channel' => $reminder->channel,
                    'reminder_metadata' => $reminder->metadata ?? [],
                ],
            ),
        );

        $this->references->handle(
            (string) $communication->getKey(),
            $reminder::class,
            (string) $reminder->getKey(),
            'engagement_reminder',
            ['reminder_type' => $reminder->reminder_type],
        );
    }
}
