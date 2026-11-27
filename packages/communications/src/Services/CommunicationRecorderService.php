<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;

class CommunicationRecorderService implements CommunicationRecorder
{
    public function markSending(string $communicationId, string $deliveryId): void
    {
        $delivery = $this->deliveryForCommunication($communicationId, $deliveryId);

        if ($delivery === null || $delivery->cancelled_at !== null) {
            return;
        }

        $communication = Communication::query()->find($communicationId);

        if ($communication !== null && ($communication->cancelled_at !== null || $communication->status === CommunicationStatus::Cancelled)) {
            return;
        }

        if ($this->isAlreadySettledForSending($delivery)) {
            return;
        }

        $delivery->status = DeliveryStatus::Sending;
        $delivery->sending_at = CarbonImmutable::now();
        $delivery->save();

        $this->recalculateCommunicationStatus($communicationId);
    }

    public function markSent(string $communicationId, string $deliveryId, array $result): void
    {
        $delivery = $this->deliveryForCommunication($communicationId, $deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->status = DeliveryStatus::Sent;
        $delivery->sent_at = CarbonImmutable::now();
        $delivery->provider_message_id = $result['provider_message_id'] ?? $delivery->provider_message_id;
        $delivery->save();

        $this->recalculateCommunicationStatus($communicationId);
    }

    public function markFailed(string $communicationId, string $deliveryId, string $failureMessage): void
    {
        $delivery = $this->deliveryForCommunication($communicationId, $deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->status = DeliveryStatus::Failed;
        $delivery->failed_at = CarbonImmutable::now();
        $delivery->failure_message = $failureMessage;
        $delivery->save();

        $this->recalculateCommunicationStatus($communicationId);
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

        return $delivery;
    }

    private function recalculateCommunicationStatus(string $communicationId): void
    {
        $communication = Communication::query()->find($communicationId);

        if ($communication === null || $communication->cancelled_at !== null || $communication->status === CommunicationStatus::Cancelled) {
            return;
        }

        $deliveries = $communication->deliveries()->get(['status']);

        if ($deliveries->isEmpty()) {
            return;
        }

        $allSent = $deliveries->every(fn (CommunicationDelivery $d) => in_array($d->status->value, [
            'sent', 'delivered', 'opened', 'read', 'clicked', 'replied',
        ], true));

        $anyFailed = $deliveries->contains(fn (CommunicationDelivery $d) => $d->status->value === 'failed');

        $communication->status = match (true) {
            $allSent => CommunicationStatus::Completed,
            $anyFailed && $deliveries->some(fn (CommunicationDelivery $d) => $d->status->value === 'sent') => CommunicationStatus::PartiallyCompleted,
            $anyFailed => CommunicationStatus::Failed,
            default => CommunicationStatus::Processing,
        };

        if ($communication->status === CommunicationStatus::Completed) {
            $communication->completed_at = CarbonImmutable::now();
        } elseif ($communication->status === CommunicationStatus::Failed) {
            $communication->failed_at = CarbonImmutable::now();
        }

        $communication->save();
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
