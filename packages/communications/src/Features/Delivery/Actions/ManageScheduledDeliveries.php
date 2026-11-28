<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Api\ScheduledDeliveryLifecycle;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\Communication;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class ManageScheduledDeliveries implements ScheduledDeliveryLifecycle
{
    public function __construct(private TransitionDeliveryAction $transitions) {}

    public function queueDue(string $communicationId, DateTimeInterface $now): void
    {
        (new Communication)->getConnection()->transaction(function () use ($communicationId, $now): void {
            $communication = $this->lockActiveCommunication($communicationId);

            if ($communication === null) {
                return;
            }

            CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Scheduled])
                ->where(function (Builder $query) use ($now): void {
                    $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now);
                })
                ->update([
                    'status' => DeliveryStatus::Queued->value,
                    'queued_at' => $now,
                ]);
        });
    }

    public function startQueued(string $communicationId): int
    {
        $transitioned = 0;

        do {
            $count = (new Communication)->getConnection()->transaction(function () use ($communicationId): int {
                $communication = $this->lockActiveCommunication($communicationId);

                if ($communication === null) {
                    return 0;
                }

                $deliveries = CommunicationDelivery::query()
                    ->where('communication_id', $communication->id)
                    ->where('status', DeliveryStatus::Queued)
                    ->orderBy('queued_at')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->limit(200)
                    ->lockForUpdate()
                    ->get();

                foreach ($deliveries as $delivery) {
                    $this->transitions->handle($delivery, DeliveryStatus::Sending);
                }

                return $deliveries->count();
            });

            $transitioned += $count;
        } while ($count === 200);

        return $transitioned;
    }

    public function cancel(string $communicationId, DateTimeInterface $now): void
    {
        (new Communication)->getConnection()->transaction(function () use ($communicationId, $now): void {
            $communication = Communication::query()
                ->whereKey($communicationId)
                ->lockForUpdate()
                ->first();

            if (! $communication instanceof Communication) {
                return;
            }

            CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Scheduled, DeliveryStatus::Queued, DeliveryStatus::Sending])
                ->update([
                    'status' => DeliveryStatus::Cancelled,
                    'cancelled_at' => $now,
                ]);
        });
    }

    public function expire(string $communicationId): void
    {
        (new Communication)->getConnection()->transaction(function () use ($communicationId): void {
            $communication = Communication::query()
                ->whereKey($communicationId)
                ->lockForUpdate()
                ->first();

            if (! $communication instanceof Communication) {
                return;
            }

            CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->whereNotIn('status', [
                    DeliveryStatus::Delivered,
                    DeliveryStatus::Opened,
                    DeliveryStatus::Read,
                    DeliveryStatus::Clicked,
                    DeliveryStatus::Replied,
                    DeliveryStatus::Bounced,
                    DeliveryStatus::Complained,
                    DeliveryStatus::Failed,
                    DeliveryStatus::Cancelled,
                    DeliveryStatus::Expired,
                    DeliveryStatus::Suppressed,
                    DeliveryStatus::Unsubscribed,
                ])
                ->lockForUpdate()
                ->eachById(fn (CommunicationDelivery $delivery) => $this->transitions->handle(
                    $delivery,
                    DeliveryStatus::Expired,
                    force: true,
                ), 100);
        });
    }

    private function lockActiveCommunication(string $communicationId): ?Communication
    {
        $communication = Communication::query()
            ->whereKey($communicationId)
            ->lockForUpdate()
            ->first();

        if (! $communication instanceof Communication) {
            return null;
        }

        if ($communication->cancelled_at !== null || in_array($communication->status, [CommunicationStatus::Cancelled, CommunicationStatus::Expired], true)) {
            return null;
        }

        return $communication;
    }
}
