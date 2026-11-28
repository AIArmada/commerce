<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\CommunicationStatusRecalculator;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;

final readonly class RecordDeliveryOutcome implements CommunicationRecorder
{
    public function __construct(private CommunicationStatusRecalculator $recalculator) {}

    public function markSending(string $communicationId, string $deliveryId): void
    {
        $this->withLockedPair($communicationId, $deliveryId, function (Communication $communication, CommunicationDelivery $delivery): void {
            if ($delivery->cancelled_at !== null || $this->isAlreadySettledForSending($delivery)) {
                return;
            }

            $delivery->status = DeliveryStatus::Sending;
            $delivery->sending_at = CarbonImmutable::now();
            $delivery->save();

            $this->recalculator->handle($communication->id);
        });
    }

    public function markSent(string $communicationId, string $deliveryId, array $result): void
    {
        $this->withLockedPair($communicationId, $deliveryId, function (Communication $communication, CommunicationDelivery $delivery) use ($result): void {
            if ($delivery->cancelled_at !== null || ! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Scheduled, DeliveryStatus::Queued, DeliveryStatus::Sending, DeliveryStatus::Accepted], true)) {
                return;
            }

            $delivery->status = DeliveryStatus::Sent;
            $delivery->sent_at = CarbonImmutable::now();
            $delivery->provider_message_id = $result['provider_message_id'] ?? $delivery->provider_message_id;
            $delivery->save();

            $this->recalculator->handle($communication->id);
        });
    }

    public function markFailed(string $communicationId, string $deliveryId, string $failureMessage): void
    {
        $this->withLockedPair($communicationId, $deliveryId, function (Communication $communication, CommunicationDelivery $delivery) use ($failureMessage): void {
            if ($delivery->cancelled_at !== null || ! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Scheduled, DeliveryStatus::Queued, DeliveryStatus::Sending, DeliveryStatus::Accepted], true)) {
                return;
            }

            $delivery->status = DeliveryStatus::Failed;
            $delivery->failed_at = CarbonImmutable::now();
            $delivery->failure_message = $failureMessage;
            $delivery->save();

            $this->recalculator->handle($communication->id);
        });
    }

    /**
     * @param  callable(Communication, CommunicationDelivery): void  $callback
     */
    private function withLockedPair(string $communicationId, string $deliveryId, callable $callback): void
    {
        (new Communication)->getConnection()->transaction(function () use ($communicationId, $deliveryId, $callback): void {
            $communication = Communication::query()
                ->whereKey($communicationId)
                ->lockForUpdate()
                ->first();

            if (! $communication instanceof Communication) {
                return;
            }

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communication->id);
            }

            if ($communication->cancelled_at !== null || in_array($communication->status, [CommunicationStatus::Cancelled, CommunicationStatus::Expired], true)) {
                return;
            }

            $delivery = CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->lockForUpdate()
                ->find($deliveryId);

            if (! $delivery instanceof CommunicationDelivery) {
                return;
            }

            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $delivery->id);
            }

            $callback($communication, $delivery);
        });
    }

    private function isAlreadySettledForSending(CommunicationDelivery $delivery): bool
    {
        if ($delivery->status instanceof DeliveryStatus && ! in_array($delivery->status, [
            DeliveryStatus::Pending,
            DeliveryStatus::Scheduled,
            DeliveryStatus::Queued,
            DeliveryStatus::Sending,
        ], true)) {
            return true;
        }

        foreach ([
            'accepted_at',
            'sent_at',
            'received_at',
            'delivered_at',
            'opened_at',
            'read_at',
            'clicked_at',
            'replied_at',
            'failed_at',
            'bounced_at',
            'complained_at',
            'expired_at',
            'suppressed_at',
            'unsubscribed_at',
        ] as $timestampColumn) {
            if ($delivery->{$timestampColumn} !== null) {
                return true;
            }
        }

        return false;
    }
}
