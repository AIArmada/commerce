<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Actions;

use AIArmada\Signals\Features\Ingestion\Api\IngestedSignalEvent;
use AIArmada\Signals\Features\Ingestion\Api\SignalIngestion;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class IngestSignal implements SignalIngestion
{
    public function __construct(private IngestSignalEvent $ingestSignalEvent) {}

    public function handle(string $trackedPropertyId, array $payload, bool $trusted): IngestedSignalEvent
    {
        $trackedProperty = TrackedProperty::query()
            ->withoutOwnerScope()
            ->find($trackedPropertyId);

        if (! $trackedProperty instanceof TrackedProperty) {
            throw new InvalidArgumentException(sprintf('Unknown tracked property [%s].', $trackedPropertyId));
        }

        $event = $this->ingestSignalEvent->handle($trackedProperty, $payload, $trusted);

        return new IngestedSignalEvent(
            id: (string) $event->getKey(),
            trackedPropertyId: (string) $event->tracked_property_id,
            eventName: (string) $event->event_name,
            eventCategory: (string) $event->event_category,
            occurredAt: CarbonImmutable::parse($event->occurred_at),
            idempotencyKey: $event->idempotency_key,
            sourceEventId: $event->source_event_id,
            properties: is_array($event->properties) ? $event->properties : [],
            revenueMinor: (int) $event->revenue_minor,
            currency: (string) $event->currency,
        );
    }
}
