<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Enums\PaymentStatus;

final readonly class ChipPaymentStatusMapper
{
    public function fromPurchaseStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'paid', 'completed' => PaymentStatus::Completed,
            'pending', 'created' => PaymentStatus::Pending,
            'failed', 'error' => PaymentStatus::Failed,
            'cancelled', 'expired' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };
    }

    public function fromCallbackPayload(array $payload): PaymentStatus
    {
        $status = $payload['status'] ?? 'unknown';

        return $this->fromPurchaseStatus($status);
    }
}
