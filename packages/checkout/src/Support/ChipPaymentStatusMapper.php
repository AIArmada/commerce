<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Enums\WebhookEventType;
use InvalidArgumentException;

final readonly class ChipPaymentStatusMapper
{
    public function fromPurchaseStatus(string $status): PaymentStatus
    {
        $statusValue = $status;
        $status = PurchaseStatus::tryFrom($statusValue);

        if ($status === null) {
            throw new InvalidArgumentException("Unsupported CHIP purchase status: {$statusValue}");
        }

        return match ($status) {
            PurchaseStatus::PAID,
            PurchaseStatus::CLEARED,
            PurchaseStatus::SETTLED => PaymentStatus::Completed,
            PurchaseStatus::CREATED,
            PurchaseStatus::SENT,
            PurchaseStatus::VIEWED,
            PurchaseStatus::OVERDUE,
            PurchaseStatus::PENDING_EXECUTE,
            PurchaseStatus::PENDING_CHARGE => PaymentStatus::Pending,
            PurchaseStatus::PENDING_CAPTURE,
            PurchaseStatus::PENDING_RELEASE,
            PurchaseStatus::PENDING_REFUND,
            PurchaseStatus::HOLD,
            PurchaseStatus::PREAUTHORIZED => PaymentStatus::Processing,
            PurchaseStatus::ERROR,
            PurchaseStatus::BLOCKED,
            PurchaseStatus::CHARGEBACK => PaymentStatus::Failed,
            PurchaseStatus::CANCELLED,
            PurchaseStatus::EXPIRED,
            PurchaseStatus::RELEASED => PaymentStatus::Cancelled,
            PurchaseStatus::REFUNDED => PaymentStatus::Refunded,
        };
    }

    public function fromCallbackPayload(array $payload): PaymentStatus
    {
        $eventTypeValue = $payload['event_type'] ?? null;

        if (! is_string($eventTypeValue)) {
            throw new InvalidArgumentException('CHIP callback payload must contain event_type.');
        }

        $eventType = WebhookEventType::tryFrom($eventTypeValue);

        if ($eventType === null) {
            throw new InvalidArgumentException("Unsupported CHIP event_type: {$eventTypeValue}");
        }

        return match ($eventType) {
            WebhookEventType::PurchasePaid,
            WebhookEventType::PurchaseCaptured,
            WebhookEventType::PurchaseSettled => PaymentStatus::Completed,
            WebhookEventType::PurchaseRefundFailure,
            WebhookEventType::PurchaseCaptureFailure,
            WebhookEventType::PurchaseReleaseFailure,
            WebhookEventType::PurchasePaymentFailure => PaymentStatus::Failed,
            WebhookEventType::PurchaseCancelled,
            WebhookEventType::PurchaseReleased => PaymentStatus::Cancelled,
            WebhookEventType::PaymentRefunded => PaymentStatus::Refunded,
            WebhookEventType::PaymentChargedBack => PaymentStatus::Failed,
            WebhookEventType::PaymentChargebackReversed => PaymentStatus::Processing,
            WebhookEventType::PayoutCreated,
            WebhookEventType::PayoutPending,
            WebhookEventType::PayoutFailed,
            WebhookEventType::PayoutSuccess => throw new InvalidArgumentException('Payout events are not checkout payment callbacks.'),
            default => $this->fromPurchaseStatus($this->requiredStatus($payload)),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredStatus(array $payload): string
    {
        $status = $payload['status'] ?? null;

        if (! is_string($status)) {
            throw new InvalidArgumentException('CHIP callback payload must contain status.');
        }

        return $status;
    }
}
