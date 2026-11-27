<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Events\CommunicationCancelled;
use AIArmada\Communications\Features\Delivery\Api\ScheduledDeliveryLifecycle;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class CancelCommunicationAction
{
    public function __construct(private readonly ScheduledDeliveryLifecycle $deliveries) {}

    public function handle(string $communicationId, ?string $reason = null): Communication
    {
        $communication = (new Communication)->getConnection()->transaction(function () use ($communicationId): Communication {
            $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

            $now = CarbonImmutable::now();
            $communication->status = CommunicationStatus::Cancelled;
            $communication->cancelled_at = $now;
            $communication->save();

            $this->deliveries->cancel($communicationId, $now);

            return $communication;
        }, attempts: 3);

        Event::dispatch(new CommunicationCancelled(
            communicationId: $communication->id,
            reason: $reason,
        ));

        return $communication;
    }
}
