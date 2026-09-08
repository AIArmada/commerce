<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Support\ChipPaymentStatusMapper as CanonicalChipPaymentStatusMapper;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus as UniversalPaymentStatus;
use InvalidArgumentException;

/**
 * Translate CHIP's canonical universal payment status into checkout's result
 * enum. CHIP owns provider status mapping; checkout owns this boundary type.
 */
final readonly class ChipPaymentStatusMapper
{
    public function fromPurchaseStatus(string $status): PaymentStatus
    {
        return $this->toCheckoutStatus(CanonicalChipPaymentStatusMapper::map($status));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
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

        if ($eventType->isPayoutEvent()) {
            throw new InvalidArgumentException('Payout events are not checkout payment callbacks.');
        }

        $status = $payload['status'] ?? null;

        return $this->toCheckoutStatus(CanonicalChipPaymentStatusMapper::mapWebhook(
            is_string($status) ? $status : null,
            $eventType->value,
        ));
    }

    private function toCheckoutStatus(UniversalPaymentStatus $status): PaymentStatus
    {
        return match ($status) {
            UniversalPaymentStatus::CREATED,
            UniversalPaymentStatus::PENDING => PaymentStatus::Pending,
            UniversalPaymentStatus::PROCESSING,
            UniversalPaymentStatus::AUTHORIZED => PaymentStatus::Processing,
            UniversalPaymentStatus::PAID => PaymentStatus::Completed,
            UniversalPaymentStatus::PARTIALLY_REFUNDED => PaymentStatus::PartiallyRefunded,
            UniversalPaymentStatus::REFUNDED => PaymentStatus::Refunded,
            UniversalPaymentStatus::FAILED,
            UniversalPaymentStatus::DISPUTED => PaymentStatus::Failed,
            UniversalPaymentStatus::CANCELLED,
            UniversalPaymentStatus::EXPIRED => PaymentStatus::Cancelled,
            UniversalPaymentStatus::REQUIRES_ACTION => PaymentStatus::Processing,
        };
    }
}
