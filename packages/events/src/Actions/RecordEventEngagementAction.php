<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\EventEngagementType;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventEngagement;
use AIArmada\Events\Models\Occurrence;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class RecordEventEngagementAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Event | Occurrence $subject,
        EventEngagementType | string $type,
        ?Model $actor = null,
        int $weight = 1,
        array $metadata = [],
    ): EventEngagement {
        $event = $subject instanceof Event ? $subject : $subject->event;

        if ($event instanceof Event && ! $event->isEngageable()) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] event is not currently engageable.',
                $event->id,
            ));
        }

        $owner = OwnerContext::fromTypeAndId($subject->owner_type, $subject->owner_id);

        return OwnerContext::withOwner($owner, function () use ($subject, $type, $actor, $weight, $metadata): EventEngagement {
            $engagement = EventEngagement::query()->updateOrCreate(
                [
                    'event_id' => $subject instanceof Event ? $subject->id : $subject->event_id,
                    'occurrence_id' => $subject instanceof Occurrence ? $subject->id : null,
                    'actor_type' => $actor?->getMorphClass(),
                    'actor_id' => $actor !== null ? (string) $actor->getKey() : null,
                    'type' => $this->normalizeType($type),
                ],
                [
                    'weight' => max(1, $weight),
                    'metadata' => $this->normalizeMetadata($metadata),
                ],
            );

            return $engagement->refresh();
        });
    }

    private function normalizeType(EventEngagementType | string $type): string
    {
        $normalized = $type instanceof EventEngagementType ? $type->value : mb_trim($type);

        if ($normalized === '') {
            throw new InvalidArgumentException('The [type] field is required.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(array $metadata): ?array
    {
        $filtered = array_filter(
            $metadata,
            static fn (mixed $value): bool => match (true) {
                $value === null => false,
                is_string($value) => $value !== '',
                default => true,
            },
        );

        return $filtered !== [] ? $filtered : null;
    }
}
