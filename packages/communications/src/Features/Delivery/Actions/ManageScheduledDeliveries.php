<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Api\ScheduledDeliveryLifecycle;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class ManageScheduledDeliveries implements ScheduledDeliveryLifecycle
{
    public function __construct(private TransitionDeliveryAction $transitions) {}

    public function queueDue(string $communicationId, DateTimeInterface $now): void
    {
        CommunicationDelivery::query()
            ->where('communication_id', $communicationId)
            ->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Scheduled])
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now);
            })
            ->update([
                'status' => DeliveryStatus::Queued->value,
                'queued_at' => $now,
            ]);
    }

    public function startQueued(string $communicationId): int
    {
        $model = new CommunicationDelivery;
        $transitioned = 0;

        do {
            $count = $model->getConnection()->transaction(function () use ($communicationId): int {
                $deliveries = CommunicationDelivery::query()
                    ->where('communication_id', $communicationId)
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

    public function expire(string $communicationId): void
    {
        $model = new CommunicationDelivery;

        $model->getConnection()->transaction(function () use ($communicationId): void {
            CommunicationDelivery::query()
                ->where('communication_id', $communicationId)
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
}
