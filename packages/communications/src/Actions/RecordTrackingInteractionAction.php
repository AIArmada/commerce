<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use Carbon\CarbonImmutable;

final class RecordTrackingInteractionAction
{
    public function handle(
        string $tokenId,
        string $interactionType,
        ?array $metadata = null,
    ): CommunicationEvent {
        $trackingToken = CommunicationTrackingToken::query()->findOrFail($tokenId);

        if ($trackingToken->first_used_at === null) {
            $trackingToken->first_used_at = CarbonImmutable::now();
        }

        $trackingToken->last_used_at = CarbonImmutable::now();
        $trackingToken->save();

        $event = new CommunicationEvent;
        $event->delivery_id = $trackingToken->delivery_id;
        $event->event = $interactionType;
        $event->source = CommunicationEventSource::Tracking;
        $event->occurred_at = CarbonImmutable::now();
        $event->received_at = CarbonImmutable::now();
        $event->metadata = $metadata;
        $event->save();

        return $event;
    }
}
