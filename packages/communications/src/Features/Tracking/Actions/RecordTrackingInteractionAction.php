<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Tracking\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\TrackingInteractionType;
use AIArmada\Communications\Features\Delivery\Api\TrackingDeliveryEvents;
use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

final class RecordTrackingInteractionAction
{
    public function __construct(
        private readonly TrackingDeliveryEvents $deliveries,
    ) {}

    public function handle(
        string $tokenId,
        TrackingInteractionType | string $interactionType,
        ?array $metadata = null,
    ): string {
        $interaction = $interactionType instanceof TrackingInteractionType
            ? $interactionType
            : TrackingInteractionType::tryFrom($interactionType);

        if ($interaction === null) {
            throw new InvalidArgumentException(
                "Unknown tracking interaction type [{$interactionType}].",
            );
        }

        return (new CommunicationTrackingToken)->getConnection()->transaction(function () use ($interaction, $metadata, $tokenId): string {
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

            if ($trackingToken->first_used_at === null) {
                $trackingToken->first_used_at = CarbonImmutable::now();
            }

            $trackingToken->last_used_at = CarbonImmutable::now();
            if (! $trackingToken->save()) {
                throw new RuntimeException('Tracking token update was cancelled.');
            }

            return $this->deliveries->record($trackingToken->delivery_id, $interaction->value, $metadata);
        });
    }
}
