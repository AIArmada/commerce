<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\EventModerationPolicy;
use Spatie\LaravelData\Data;

final class EventReviewSchemaData extends Data
{
    /**
     * @param  array<string, array<string, mixed>>  $reasonCodes
     * @param  array<int, string>  $actions
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $currentStatus,
        public readonly array $actions = [],
        public readonly array $reasonCodes = [],
    ) {}

    public static function fromEvent(Event $event): self
    {
        $currentStatus = $event->moderation_status instanceof EventModerationStatus
            ? $event->moderation_status
            : EventModerationStatus::Pending;

        return new self(
            eventId: $event->id,
            currentStatus: $currentStatus->value,
            actions: EventModerationPolicy::allowedActionsFor($currentStatus),
            reasonCodes: EventModerationPolicy::reasonCodes(),
        );
    }
}
