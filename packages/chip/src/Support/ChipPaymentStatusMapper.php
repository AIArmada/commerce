<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use InvalidArgumentException;

final class ChipPaymentStatusMapper
{
    public static function map(string $chipStatus): PaymentStatus
    {
        $status = PurchaseStatus::tryFrom($chipStatus);

        if ($status === null) {
            throw new InvalidArgumentException("Unsupported CHIP purchase status: {$chipStatus}");
        }

        return match ($status) {
            PurchaseStatus::CREATED => PaymentStatus::CREATED,
            PurchaseStatus::SENT,
            PurchaseStatus::VIEWED,
            PurchaseStatus::OVERDUE,
            PurchaseStatus::PENDING_EXECUTE,
            PurchaseStatus::PENDING_CHARGE => PaymentStatus::PENDING,
            PurchaseStatus::PENDING_CAPTURE,
            PurchaseStatus::PENDING_RELEASE,
            PurchaseStatus::PENDING_REFUND => PaymentStatus::PROCESSING,
            PurchaseStatus::HOLD,
            PurchaseStatus::PREAUTHORIZED => PaymentStatus::AUTHORIZED,
            PurchaseStatus::PAID,
            PurchaseStatus::CLEARED,
            PurchaseStatus::SETTLED => PaymentStatus::PAID,
            PurchaseStatus::REFUNDED => PaymentStatus::REFUNDED,
            PurchaseStatus::CANCELLED,
            PurchaseStatus::RELEASED => PaymentStatus::CANCELLED,
            PurchaseStatus::EXPIRED => PaymentStatus::EXPIRED,
            PurchaseStatus::CHARGEBACK => PaymentStatus::DISPUTED,
            PurchaseStatus::ERROR,
            PurchaseStatus::BLOCKED => PaymentStatus::FAILED,
        };
    }
}
