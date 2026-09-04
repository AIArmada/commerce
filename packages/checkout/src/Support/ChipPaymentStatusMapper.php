<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Enums\PaymentStatus;

final readonly class ChipPaymentStatusMapper
{
    public function fromPurchaseStatus(string $status): PaymentStatus
    {
        $status = mb_strtolower(mb_trim($status));

        return match ($status) {
            'paid', 'completed' => PaymentStatus::Completed,
            'pending', 'created' => PaymentStatus::Pending,
            'failed', 'error' => PaymentStatus::Failed,
            'cancelled', 'expired' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            'partially_refunded' => PaymentStatus::PartiallyRefunded,
            'pending_refund', 'attempted_refund' => PaymentStatus::Processing,
            default => PaymentStatus::Processing,
        };
    }

    public function fromCallbackPayload(array $payload): PaymentStatus
    {
        $status = $payload['status'] ?? 'unknown';

        return $this->fromPurchaseStatus(is_string($status) ? $status : 'unknown');
    }
}
