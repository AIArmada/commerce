<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Data\PlannedDeliveryData;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliveryPlanned;
use AIArmada\Communications\Features\Aggregate\Api\DeliveryReferenceValidator;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Throwable;

final class PlanCommunicationDeliveriesAction
{
    public function __construct(private readonly DeliveryReferenceValidator $references) {}

    /**
     * @param  array<PlannedDeliveryData>  $planned
     * @return Collection<int, CommunicationDelivery>
     */
    public function handle(string $communicationId, array $planned): Collection
    {
        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        $maxDeliveries = max(1, (int) config('communications.planning.max_deliveries', 500));

        if (count($planned) > $maxDeliveries) {
            throw new InvalidArgumentException(
                'Cannot plan ' . count($planned) . " deliveries in one call; maximum is {$maxDeliveries}.",
            );
        }

        $this->references->validate(
            $communicationId,
            array_values(array_unique(array_map(fn (PlannedDeliveryData $plan): string => $plan->recipientId, $planned))),
            array_values(array_unique(array_filter(array_map(fn (PlannedDeliveryData $plan): ?string => $plan->contentId, $planned), fn (?string $id): bool => $id !== null))),
        );

        $rows = [];

        foreach ($planned as $plan) {
            $contentId = $plan->contentId;
            $maxAttempts = $plan->maxAttempts;

            if ($maxAttempts !== null && $maxAttempts < 0) {
                throw new InvalidArgumentException('Planned delivery max attempts must not be negative.');
            }

            $scheduledAt = $plan->scheduledAt;

            $rows[] = [
                'recipient_id' => $plan->recipientId,
                'content_id' => $contentId,
                'channel' => $plan->channel,
                'destination_ciphertext' => $plan->destinationCiphertext,
                'destination_hash' => $plan->destinationHash,
                'destination_hint' => $plan->destinationHint,
                'max_attempts' => $maxAttempts ?? (int) config('communications.defaults.max_attempts', 3),
                'scheduled_at' => is_string($scheduledAt) ? $this->parseScheduledAt($scheduledAt) : null,
            ];
        }

        $connection = (new Communication)->getConnection();

        $deliveries = $connection->transaction(function () use ($communicationId, $rows): Collection {
            $communication = Communication::query()
                ->whereKey($communicationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($communication->cancelled_at !== null || in_array($communication->status, [CommunicationStatus::Cancelled, CommunicationStatus::Expired], true)) {
                throw new InvalidArgumentException('Cannot plan deliveries for a cancelled or expired communication.');
            }

            $records = new Collection;

            foreach ($rows as $row) {
                $delivery = new CommunicationDelivery;
                $delivery->communication_id = $communication->id;
                $delivery->recipient_id = $row['recipient_id'];
                $delivery->content_id = $row['content_id'];
                $delivery->channel = $row['channel'];
                $delivery->destination_ciphertext = $row['destination_ciphertext'];
                $delivery->destination_hash = $row['destination_hash'];
                $delivery->destination_hint = $row['destination_hint'];
                $delivery->status = DeliveryStatus::Pending;
                $delivery->attempt_count = 0;
                $delivery->max_attempts = $row['max_attempts'];
                $delivery->scheduled_at = $row['scheduled_at'];
                $delivery->save();

                $records->push($delivery);
            }

            return $records;
        });

        foreach ($deliveries as $delivery) {
            $owner = $delivery->owner_type !== null || $delivery->owner_id !== null
                ? OwnerContext::fromTypeAndId($delivery->owner_type, $delivery->owner_id)
                : null;

            $connection->afterCommit(function () use ($delivery, $owner): void {
                if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                    OwnerContext::withOwner($owner, fn () => Event::dispatch(new DeliveryPlanned(
                        deliveryId: $delivery->id,
                        communicationId: $delivery->communication_id,
                        channel: $delivery->channel,
                    )));

                    return;
                }

                Event::dispatch(new DeliveryPlanned(
                    deliveryId: $delivery->id,
                    communicationId: $delivery->communication_id,
                    channel: $delivery->channel,
                ));
            });
        }

        return $deliveries;
    }

    private function parseScheduledAt(string $scheduledAt): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($scheduledAt);
        } catch (Throwable) {
            throw new InvalidArgumentException("Invalid planned delivery scheduled date [{$scheduledAt}].");
        }
    }
}
