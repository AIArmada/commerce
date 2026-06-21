<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;

class CommunicationRecorderService implements CommunicationRecorder
{
    public function createCommunication(CommunicationContextData $context): Communication
    {
        $communication = new Communication;
        $communication->direction = $context->direction;
        $communication->category = $context->category;
        $communication->priority = $context->priority;
        $communication->purpose = $context->purpose;
        $communication->status = CommunicationStatus::Draft;
        $communication->idempotency_key = $context->idempotencyKey;
        $communication->locale = $context->locale;
        $communication->timezone = $context->timezone;
        $communication->scheduled_at = $context->scheduledAt !== null ? CarbonImmutable::parse($context->scheduledAt) : null;
        $communication->expires_at = $context->expiresAt !== null ? CarbonImmutable::parse($context->expiresAt) : null;
        $communication->metadata = $context->metadata;
        $communication->subject_type = $context->subjectType;
        $communication->subject_id = $context->subjectId;
        $communication->sender_type = $context->senderType;
        $communication->sender_id = $context->senderId;
        $communication->batch_id = $context->batchId;
        $communication->thread_id = $context->threadId;
        $communication->parent_id = $context->parentId;
        $communication->save();

        return $communication;
    }

    public function markSending(string $communicationId, string $deliveryId): void
    {
        $delivery = CommunicationDelivery::query()->find($deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->status = DeliveryStatus::Sending;
        $delivery->sending_at = CarbonImmutable::now();
        $delivery->save();

        $this->recalculateCommunicationStatus($communicationId);
    }

    public function markSent(string $communicationId, string $deliveryId, array $result): void
    {
        $delivery = CommunicationDelivery::query()->find($deliveryId);

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
        $delivery = CommunicationDelivery::query()->find($deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->status = DeliveryStatus::Failed;
        $delivery->failed_at = CarbonImmutable::now();
        $delivery->failure_message = $failureMessage;
        $delivery->save();

        $this->recalculateCommunicationStatus($communicationId);
    }

    public function cancelCommunication(string $communicationId): void
    {
        $communication = Communication::query()->find($communicationId);

        if ($communication === null) {
            return;
        }

        $communication->status = CommunicationStatus::Cancelled;
        $communication->cancelled_at = CarbonImmutable::now();
        $communication->save();
    }

    private function recalculateCommunicationStatus(string $communicationId): void
    {
        $communication = Communication::query()->find($communicationId);

        if ($communication === null) {
            return;
        }

        $deliveries = $communication->deliveries()->get(['status']);

        if ($deliveries->isEmpty()) {
            return;
        }

        $allSent = $deliveries->every(fn ($d) => in_array($d->status, [
            'sent', 'delivered', 'opened', 'read', 'clicked', 'replied',
        ], true));

        $anyFailed = $deliveries->contains(fn ($d) => $d->status === 'failed');

        $communication->status = match (true) {
            $allSent => CommunicationStatus::Completed,
            $anyFailed && $deliveries->some(fn ($d) => $d->status === 'sent') => CommunicationStatus::PartiallyCompleted,
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
}
