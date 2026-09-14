<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\TrackingInteractionType;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RecordTrackingInteractionAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $tokenId,
        TrackingInteractionType | string $interactionType,
        ?array $metadata = null,
    ): CommunicationEvent {
        $interaction = $interactionType instanceof TrackingInteractionType
            ? $interactionType
            : TrackingInteractionType::tryFrom($interactionType);

        if ($interaction === null) {
            throw new InvalidArgumentException(
                "Unknown tracking interaction type [{$interactionType}].",
            );
        }

        return DB::transaction(function () use ($interaction, $metadata, $tokenId): CommunicationEvent {
            $trackingToken = CommunicationTrackingToken::query()
                ->lockForUpdate()
                ->findOrFail($tokenId);

            if (CommunicationTrackingToken::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationTrackingToken::class, $tokenId);
            }

            if ($trackingToken->revoked_at !== null) {
                throw new RuntimeException("Tracking token {$tokenId} has been revoked.");
            }

            if ($trackingToken->expires_at !== null && $trackingToken->expires_at->lte(CarbonImmutable::now())) {
                throw new RuntimeException("Tracking token {$tokenId} has expired.");
            }

            $delivery = $trackingToken->delivery()->firstOrFail();

            if ($trackingToken->first_used_at === null) {
                $trackingToken->first_used_at = CarbonImmutable::now();
            }

            $trackingToken->last_used_at = CarbonImmutable::now();
            $trackingToken->save();

            $event = new CommunicationEvent;
            $event->communication_id = $delivery->communication_id;
            $event->delivery_id = $delivery->id;
            $event->event = $interaction->value;
            $event->source = CommunicationEventSource::Tracking;
            $event->occurred_at = CarbonImmutable::now();
            $event->received_at = CarbonImmutable::now();
            $event->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;
            $event->save();

            return $event;
        });
    }
}
