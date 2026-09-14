<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
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
use InvalidArgumentException;
use Spatie\LaravelData\Optional;
use Throwable;

final class PlanCommunicationDeliveriesAction
{
    /**
     * @param  array<PlannedDeliveryData>  $planned
     * @return Collection<int, CommunicationDelivery>
     */
    public function handle(string $communicationId, array $planned): Collection
    {
        $communication = Communication::query()->findOrFail($communicationId);

        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        $maxDeliveries = max(1, (int) config('communications.planning.max_deliveries', 500));

        if (count($planned) > $maxDeliveries) {
            throw new InvalidArgumentException(
                'Cannot plan ' . count($planned) . " deliveries in one call; maximum is {$maxDeliveries}.",
            );
        }

        $recipients = CommunicationRecipient::query()
            ->where('communication_id', $communication->id)
            ->whereKey(collect($planned)->map(fn (PlannedDeliveryData $plan): string => $plan->recipientId)->all())
            ->get()
            ->keyBy('id');

        $contentIds = collect($planned)
            ->map(fn (PlannedDeliveryData $plan): mixed => $plan->contentId instanceof Optional ? null : $plan->contentId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingContentIds = $contentIds === []
            ? []
            : CommunicationContent::query()
                ->where('communication_id', $communication->id)
                ->whereKey($contentIds)
                ->pluck('id')
                ->all();

        $rows = [];

        foreach ($planned as $plan) {
            $recipient = $recipients->get($plan->recipientId);

            if ($recipient === null) {
                throw (new ModelNotFoundException)->setModel(CommunicationRecipient::class, [$plan->recipientId]);
            }

            $contentId = $plan->contentId instanceof Optional ? null : $plan->contentId;

            if ($contentId !== null && ! in_array($contentId, $existingContentIds, true)) {
                throw (new ModelNotFoundException)->setModel(CommunicationContent::class, [$contentId]);
            }

            $maxAttempts = $plan->maxAttempts instanceof Optional ? null : $plan->maxAttempts;

            if ($maxAttempts !== null && $maxAttempts < 0) {
                throw new InvalidArgumentException('Planned delivery max attempts must not be negative.');
            }

            $scheduledAt = $plan->scheduledAt instanceof Optional ? null : $plan->scheduledAt;

            $rows[] = [
                'recipient_id' => $recipient->id,
                'content_id' => $contentId,
                'channel' => $plan->channel,
                'destination_ciphertext' => $plan->destinationCiphertext,
                'destination_hash' => $plan->destinationHash,
                'destination_hint' => $plan->destinationHint,
                'max_attempts' => $maxAttempts ?? (int) config('communications.defaults.max_attempts', 3),
                'scheduled_at' => is_string($scheduledAt) ? $this->parseScheduledAt($scheduledAt) : null,
            ];
        }

        $deliveries = DB::transaction(function () use ($communication, $rows): Collection {
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
            Event::dispatch(new DeliveryPlanned(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            ));
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
