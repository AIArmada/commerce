<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Notifications\EventChangeNoticeNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EventNotificationDispatcher implements EventChangeNoticeNotificationDispatcher
{
    public function __construct(
        private readonly EventChangeNoticeAudienceResolver $audienceResolver,
        private readonly CommunicationManager $manager,
    ) {}

    public function dispatch(EventChangeLog $changeLog): void
    {
        $changeLog->loadMissing(['event', 'eventUpdate']);

        $event = $changeLog->event;
        if ($event === null) {
            return;
        }

        $audienceScope = in_array($changeLog->impact_level, ['critical', 'high'], true)
            ? 'registrants'
            : 'followers';
        $recipients = $changeLog->eventUpdate === null
            ? new Collection
            : collect($this->audienceResolver->resolve($changeLog->eventUpdate, $audienceScope));

        $notification = new EventChangeNoticeNotification(
            'Event Change Notice',
            $changeLog->reason,
        );

        if ($recipients->isNotEmpty() || $audienceScope !== 'registrants') {
            $this->notifyRecipients($recipients, $changeLog, $event, $notification, $audienceScope);

            return;
        }

        EventRegistration::query()
            ->where('event_id', $event->getKey())
            ->whereIn('status', EventRegistration::CAPACITY_BLOCKING_STATUSES)
            ->chunkById(500, function ($registrations) use ($changeLog, $event, $notification, $audienceScope): void {
                $this->notifyRecipients($registrations, $changeLog, $event, $notification, $audienceScope);
            });
    }

    /**
     * @param  iterable<int, mixed>  $recipients
     */
    private function notifyRecipients(
        iterable $recipients,
        EventChangeLog $changeLog,
        Event $event,
        EventChangeNoticeNotification $notification,
        string $audienceScope,
    ): void {
        foreach ($recipients as $recipient) {
            if (! $recipient instanceof Model) {
                continue;
            }

            $key = $recipient->getKey();

            if (! is_string($key) || ! Str::isUuid($key)) {
                continue;
            }

            if (! $this->hasMailDestination($recipient, $notification)) {
                continue;
            }

            $this->manager->notify($recipient, $notification, CommunicationContextData::from([
                'category' => 'transactional',
                'purpose' => 'event-change-notice',
                'subjectType' => $event->getMorphClass(),
                'subjectId' => (string) $event->getKey(),
                'metadata' => [
                    'event_id' => (string) $event->getKey(),
                    'event_change_log_id' => (string) $changeLog->getKey(),
                    'audience_scope' => $audienceScope,
                ],
            ]));
        }
    }

    private function hasMailDestination(Model $recipient, EventChangeNoticeNotification $notification): bool
    {
        if (method_exists($recipient, 'routeNotificationForMail')) {
            return $recipient->routeNotificationForMail($notification) !== null;
        }

        $email = $recipient->getAttribute('email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
