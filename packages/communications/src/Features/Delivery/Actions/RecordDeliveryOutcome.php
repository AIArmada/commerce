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
        $delivery = $this->deliveryForCommunication($communicationId, $deliveryId);

        if ($delivery === null || $delivery->cancelled_at !== null) {
            return;
        }

        if ($this->isAlreadySettledForSending($delivery)) {
            return;
        }

        $delivery->status = DeliveryStatus::Sending;
        $delivery->sending_at = CarbonImmutable::now();
        $delivery->save();

        $this->recalculator->handle($communicationId);
    }

    public function markSent(string $communicationId, string $deliveryId, array $result): void
    {
        $delivery = $this->deliveryForCommunication($communicationId, $deliveryId);

        if ($delivery === null || $delivery->cancelled_at !== null || ! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Scheduled, DeliveryStatus::Queued, DeliveryStatus::Sending, DeliveryStatus::Accepted], true)) {
            return;
        }

        $delivery->status = DeliveryStatus::Sent;
        $delivery->sent_at = CarbonImmutable::now();
        $delivery->provider_message_id = $result['provider_message_id'] ?? $delivery->provider_message_id;
        $delivery->save();

        $this->recalculator->handle($communicationId);
    }

    public function markFailed(string $communicationId, string $deliveryId, string $failureMessage): void
    {
        $delivery = $this->deliveryForCommunication($communicationId, $deliveryId);

        if ($delivery === null || $delivery->cancelled_at !== null || ! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Scheduled, DeliveryStatus::Queued, DeliveryStatus::Sending, DeliveryStatus::Accepted], true)) {
            return;
        }

        $delivery->status = DeliveryStatus::Failed;
        $delivery->failed_at = CarbonImmutable::now();
        $delivery->failure_message = $failureMessage;
        $delivery->save();

        $this->recalculator->handle($communicationId);
    }

    private function deliveryForCommunication(string $communicationId, string $deliveryId): ?CommunicationDelivery
    {
        $delivery = CommunicationDelivery::query()
            ->where('communication_id', $communicationId)
            ->find($deliveryId);

        if ($delivery === null) {
            return null;
        }

        if (CommunicationDelivery::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $deliveryId);
        }

        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        $communication = Communication::query()->find($communicationId);

        if ($communication === null || $communication->cancelled_at !== null || in_array($communication->status, [CommunicationStatus::Cancelled, CommunicationStatus::Expired], true)) {
            return null;
        }

        return $delivery;
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
