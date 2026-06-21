<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Data\PlannedDeliveryData;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliveryPlanned;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

final class PlanCommunicationDeliveriesAction
{
    public function __construct(
    ) {}

    /**
     * @param  array<PlannedDeliveryData>  $planned
     * @return Collection<int, CommunicationDelivery>
     */
    public function handle(string $communicationId, array $planned): Collection
    {
        $communication = Communication::query()->findOrFail($communicationId);

        $deliveries = new Collection;

        foreach ($planned as $plan) {
            $delivery = new CommunicationDelivery;
            $delivery->communication_id = $communication->id;
            $delivery->recipient_id = $plan->recipientId;
            $delivery->content_id = $plan->contentId;
            $delivery->channel = $plan->channel;
            $delivery->destination_ciphertext = $plan->destinationCiphertext;
            $delivery->destination_hash = $plan->destinationHash;
            $delivery->destination_hint = $plan->destinationHint;
            $delivery->status = DeliveryStatus::Pending;
            $delivery->attempt_count = 0;
            $delivery->max_attempts = $plan->maxAttempts ?? 3;
            $delivery->scheduled_at = $plan->scheduledAt !== null ? CarbonImmutable::parse($plan->scheduledAt) : null;
            $delivery->save();

            Event::dispatch(new DeliveryPlanned(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            ));

            $deliveries->push($delivery);
        }

        return $deliveries;
    }
}
