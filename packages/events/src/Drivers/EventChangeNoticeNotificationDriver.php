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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EventChangeNoticeNotificationDriver implements EventChangeNoticeNotificationDispatcher
{
    public function __construct(
        private readonly CommunicationManager $manager,
        private readonly EventChangeNoticeAudienceResolver $audienceResolver,
    ) {}

    private const int RECIPIENT_BATCH_SIZE = 500;

    public function dispatch(EventChangeNoticeNotificationData $notice): void
    {
        $notification = new EventChangeNoticeNotification($notice);
        $subjectType = $notice->eventType ?? $this->defaultEventType();

        foreach ($this->recipientBatches($notice) as $recipients) {
            $notifiables = $this->loadNotifiables($recipients);

            foreach ($recipients as $recipient) {
                $notifiable = $notifiables[$this->recipientKey($recipient)] ?? null;

                if (! $notifiable instanceof Model || ! $this->hasMailDestination($notifiable, $notification)) {
                    continue;
                }

                $this->manager->notify($notifiable, $notification, CommunicationContextData::from([
                    'category' => 'transactional',
                    'purpose' => 'event-change-notice',
                    'subjectType' => $subjectType,
                    'subjectId' => $notice->eventId,
                    'metadata' => [
                        'event_id' => $notice->eventId,
                        'event_change_log_id' => $notice->changeLogId,
                        'audience_scope' => $notice->audienceScope,
                    ],
                ]));
            }
        }
    }

    /**
     * @return iterable<int, list<EventNotificationRecipientData>>
     */
    private function recipientBatches(EventChangeNoticeNotificationData $notice): iterable
    {
        $resolved = false;

        foreach ($this->uniqueRecipientBatches($this->configuredRecipients($notice)) as $batch) {
            $resolved = true;

            yield $batch;
        }

        if (! $resolved && $notice->audienceScope === 'registrants') {
            yield from $this->registrationRecipientBatches($notice->eventId);
        }
    }

    /**
     * @return iterable<int, EventNotificationRecipientData>
     */
    private function configuredRecipients(EventChangeNoticeNotificationData $notice): iterable
    {
        yield from $notice->recipients;

        if ($notice->updateId !== null) {
            yield from $this->audienceResolver->resolve($notice->updateId, $notice->audienceScope);
        }
    }

    /**
     * @param  iterable<EventNotificationRecipientData>  $recipients
     * @return iterable<int, list<EventNotificationRecipientData>>
     */
    private function uniqueRecipientBatches(iterable $recipients): iterable
    {
        $seen = [];
        $batch = [];

        foreach ($recipients as $recipient) {
            if (! $recipient instanceof EventNotificationRecipientData || $recipient->notifiableId === '') {
                continue;
            }

            $key = $this->recipientKey($recipient);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $batch[] = $recipient;

            if (count($batch) === self::RECIPIENT_BATCH_SIZE) {
                yield $batch;
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * @return iterable<int, list<EventNotificationRecipientData>>
     */
    private function registrationRecipientBatches(string $eventId): iterable
    {
        $registrationClass = ModelResolver::registrationClass();
        $statuses = config(
            'events.lifecycle.registration.capacity_blocking_statuses',
            ['pending', 'confirmed', 'checked_in'],
        );

        $batch = [];

        foreach ($registrationClass::query()
            ->where('event_id', $eventId)
            ->whereIn('status', $statuses)
            ->lazyById(self::RECIPIENT_BATCH_SIZE) as $registration) {
            if ($registration instanceof Model) {
                $batch[] = new EventNotificationRecipientData(
                    notifiableType: $registration->getMorphClass(),
                    notifiableId: (string) $registration->getKey(),
                );
            }

            if (count($batch) >= self::RECIPIENT_BATCH_SIZE) {
                yield $batch;
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * @param  list<EventNotificationRecipientData>  $recipients
     * @return array<string, Model>
     */
    private function loadNotifiables(array $recipients): array
    {
        $grouped = [];

        foreach ($recipients as $recipient) {
            $grouped[$recipient->notifiableType][] = $recipient->notifiableId;
        }

        $notifiables = [];

        foreach ($grouped as $type => $ids) {
            $modelClass = Relation::getMorphedModel($type) ?? $type;

            if (! is_a($modelClass, Model::class, true)) {
                continue;
            }

            $query = $modelClass::query();

            $this->eagerLoadNotificationRelations($query, $modelClass);

            foreach ($query->whereKey($ids)->get() as $notifiable) {
                if ($notifiable instanceof Model) {
                    $notifiables[$type . ':' . $notifiable->getKey()] = $notifiable;
                }
            }
        }

        return $notifiables;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<Model>  $modelClass
     */
    private function eagerLoadNotificationRelations(Builder $query, string $modelClass): void
    {
        $registrationClass = ModelResolver::registrationClass();

        if (! is_a($modelClass, $registrationClass, true)) {
            return;
        }

        $query->with([
            'participants' => static fn (HasMany $participants): HasMany => $participants
                ->orderByDesc('is_primary')
                ->orderBy('created_at'),
            'participants.contactMethods',
        ]);
    }

    private function recipientKey(EventNotificationRecipientData $recipient): string
    {
        return $recipient->notifiableType . ':' . $recipient->notifiableId;
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
