<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Data\EventChangeNoticeNotificationData;
use AIArmada\Events\Data\EventNotificationRecipientData;
use AIArmada\Events\Notifications\EventChangeNoticeNotification;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

final class EventChangeNoticeNotificationDriver implements EventChangeNoticeNotificationDispatcher
{
    public function __construct(
        private readonly CommunicationManager $manager,
        private readonly EventChangeNoticeAudienceResolver $audienceResolver,
    ) {}

    public function dispatch(EventChangeNoticeNotificationData $notice): void
    {
        $recipients = $this->resolveRecipients($notice);

        if ($recipients === []) {
            return;
        }

        $notification = new EventChangeNoticeNotification($notice);

        foreach ($recipients as $recipient) {
            if (! Str::isUuid($recipient->notifiableId)) {
                continue;
            }

            $notifiable = $this->resolveNotifiable($recipient);

            if (! $notifiable instanceof Model || ! $this->hasMailDestination($notifiable, $notification)) {
                continue;
            }

            $this->manager->notify($notifiable, $notification, CommunicationContextData::from([
                'category' => 'transactional',
                'purpose' => 'event-change-notice',
                'subjectType' => $notice->eventType ?? $this->defaultEventType(),
                'subjectId' => $notice->eventId,
                'metadata' => [
                    'event_id' => $notice->eventId,
                    'event_change_log_id' => $notice->changeLogId,
                    'audience_scope' => $notice->audienceScope,
                ],
            ]));
        }
    }

    /**
     * @return list<EventNotificationRecipientData>
     */
    private function resolveRecipients(EventChangeNoticeNotificationData $notice): array
    {
        $recipients = $notice->recipients;

        if ($notice->updateId !== null) {
            foreach ($this->audienceResolver->resolve($notice->updateId, $notice->audienceScope) as $recipient) {
                if ($recipient instanceof EventNotificationRecipientData) {
                    $recipients[] = $recipient;
                }
            }
        }

        if ($recipients !== [] || $notice->audienceScope !== 'registrants') {
            return $recipients;
        }

        return $this->registrationRecipients($notice->eventId);
    }

    /**
     * @return list<EventNotificationRecipientData>
     */
    private function registrationRecipients(string $eventId): array
    {
        $registrationClass = ModelResolver::registrationClass();
        $statuses = config(
            'events.lifecycle.registration.capacity_blocking_statuses',
            ['pending', 'confirmed', 'checked_in'],
        );

        $recipients = [];

        $registrationClass::query()
            ->where('event_id', $eventId)
            ->whereIn('status', $statuses)
            ->chunkById(500, function (iterable $registrations) use (&$recipients): void {
                foreach ($registrations as $registration) {
                    if (! $registration instanceof Model) {
                        continue;
                    }

                    $recipients[] = new EventNotificationRecipientData(
                        notifiableType: $registration->getMorphClass(),
                        notifiableId: (string) $registration->getKey(),
                    );
                }
            });

        return $recipients;
    }

    private function resolveNotifiable(EventNotificationRecipientData $recipient): ?Model
    {
        $modelClass = Relation::getMorphedModel($recipient->notifiableType) ?? $recipient->notifiableType;

        if (! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $notifiable = $modelClass::query()->find($recipient->notifiableId);

        return $notifiable instanceof Model ? $notifiable : null;
    }

    private function hasMailDestination(Model $recipient, EventChangeNoticeNotification $notification): bool
    {
        if (method_exists($recipient, 'routeNotificationForMail')) {
            return $recipient->routeNotificationForMail($notification) !== null;
        }

        $email = $recipient->getAttribute('email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function defaultEventType(): string
    {
        $eventClass = ModelResolver::eventClass();

        return (new $eventClass)->getMorphClass();
    }
}
