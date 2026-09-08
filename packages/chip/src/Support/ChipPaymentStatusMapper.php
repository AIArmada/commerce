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

    /**
     * Map a webhook using its event type as the authoritative status signal.
     *
     * CHIP event names describe transitions more precisely than the status
     * field on some payment payloads, so a recognised event always wins.
     */
    public static function mapWebhook(?string $chipStatus, string $eventType): PaymentStatus
    {
        $eventStatus = match ($eventType) {
            'purchase.created' => PaymentStatus::CREATED,
            'purchase.paid', 'purchase.captured', 'purchase.settled' => PaymentStatus::PAID,
            'purchase.payment_failure', 'purchase.refund_failure',
            'purchase.capture_failure', 'purchase.release_failure' => PaymentStatus::FAILED,
            'purchase.cancelled', 'purchase.released' => PaymentStatus::CANCELLED,
            'purchase.hold', 'purchase.preauthorized' => PaymentStatus::AUTHORIZED,
            'purchase.pending_execute', 'purchase.pending_charge',
            'purchase.viewed' => PaymentStatus::PENDING,
            'purchase.pending_capture', 'purchase.pending_release',
            'purchase.pending_refund', 'purchase.pending_recurring_token_delete' => PaymentStatus::PROCESSING,
            'payment.refunded' => PaymentStatus::REFUNDED,
            'payment.charged_back' => PaymentStatus::DISPUTED,
            'payment.chargeback_reversed' => PaymentStatus::PROCESSING,
            'payout.created', 'payout.pending' => PaymentStatus::PENDING,
            'payout.failed' => PaymentStatus::FAILED,
            'payout.success' => PaymentStatus::PAID,
            default => null,
        };

        if ($eventStatus !== null) {
            return $eventStatus;
        }

        if ($chipStatus === null) {
            throw new InvalidArgumentException('CHIP webhook payload contains an unsupported status.');
        }

        return self::map($chipStatus);
    }
}
