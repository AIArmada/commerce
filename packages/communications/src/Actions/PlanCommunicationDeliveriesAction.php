<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Data\PlannedDeliveryData;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliveryPlanned;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelData\Optional;

final class PlanCommunicationDeliveriesAction
{
    /**
     * @param  array<PlannedDeliveryData>  $planned
     * @return Collection<int, CommunicationDelivery>
     */
    public function handle(string $communicationId, array $planned): Collection
    {
        $communication = Communication::query()->findOrFail($communicationId);

        $deliveries = DB::transaction(function () use ($communication, $planned): Collection {
            $records = new Collection;

            foreach ($planned as $plan) {
                $recipient = CommunicationRecipient::query()
                    ->where('communication_id', $communication->id)
                    ->find($plan->recipientId);

                if ($recipient === null) {
                    throw (new ModelNotFoundException)->setModel(CommunicationRecipient::class, [$plan->recipientId]);
                }

                $contentId = $plan->contentId instanceof Optional ? null : $plan->contentId;

                if ($contentId !== null) {
                    $contentExists = CommunicationContent::query()
                        ->where('communication_id', $communication->id)
                        ->whereKey($contentId)
                        ->exists();

                    if (! $contentExists) {
                        throw (new ModelNotFoundException)->setModel(CommunicationContent::class, [$contentId]);
                    }
                }

                $delivery = new CommunicationDelivery;
                $delivery->communication_id = $communication->id;
                $delivery->recipient_id = $recipient->id;
                $delivery->content_id = $contentId;
                $delivery->channel = $plan->channel;
                $delivery->destination_ciphertext = $plan->destinationCiphertext;
                $delivery->destination_hash = $plan->destinationHash;
                $delivery->destination_hint = $plan->destinationHint;
                $delivery->status = DeliveryStatus::Pending;
                $delivery->attempt_count = 0;
                $delivery->max_attempts = is_int($plan->maxAttempts)
                    ? $plan->maxAttempts
                    : (int) config('communications.defaults.max_attempts', 3);
                $delivery->scheduled_at = is_string($plan->scheduledAt)
                    ? CarbonImmutable::parse($plan->scheduledAt)
                    : null;
                $delivery->save();

                $records->push($delivery);
            }

            return $records;
        });

        foreach ($deliveries as $delivery) {
            Event::dispatch(new DeliveryPlanned(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            ));
        }

        return $deliveries;
    }
}
