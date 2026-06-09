<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

final class ChipPaymentStatusMapper
{
    public static function map(string $chipStatus): PaymentStatus
    {
        return match ($chipStatus) {
            'created' => PaymentStatus::CREATED,
            'sent', 'viewed', 'pending_execute', 'pending_charge' => PaymentStatus::PENDING,
            'attempted_capture', 'attempted_refund', 'attempted_recurring', 'pending_refund' => PaymentStatus::PROCESSING,
            'pending_capture' => PaymentStatus::AUTHORIZED,
            'pending_release' => PaymentStatus::AUTHORIZED,
            'hold' => PaymentStatus::AUTHORIZED,
            'preauthorized' => PaymentStatus::AUTHORIZED,
            'paid', 'captured', 'paid_authorized', 'recurring_successful', 'cleared', 'settled' => PaymentStatus::PAID,
            'refunded' => PaymentStatus::REFUNDED,
            'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
            'cancelled', 'released' => PaymentStatus::CANCELLED,
            'expired', 'overdue' => PaymentStatus::EXPIRED,
            'chargeback' => PaymentStatus::DISPUTED,
            'error' => PaymentStatus::FAILED,
            'blocked' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
