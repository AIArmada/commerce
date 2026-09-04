<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * CHIP Webhook Event Types
 *
 * All available event types that can be emitted by CHIP webhooks.
 *
 * @see https://docs.chip-in.asia/chip-collect/api-reference/webhooks/create
 */
enum WebhookEventType: string
{
    // Purchase events
    case PurchaseCreated = 'purchase.created';
    case PurchasePaid = 'purchase.paid';
    case PurchasePaymentFailure = 'purchase.payment_failure';
    case PurchaseRefundFailure = 'purchase.refund_failure';
    case PurchaseCaptureFailure = 'purchase.capture_failure';
    case PurchaseReleaseFailure = 'purchase.release_failure';
    case PurchasePendingExecute = 'purchase.pending_execute';
    case PurchasePendingCharge = 'purchase.pending_charge';
    case PurchaseCancelled = 'purchase.cancelled';
    case PurchaseHold = 'purchase.hold';
    case PurchaseCaptured = 'purchase.captured';
    case PurchasePendingCapture = 'purchase.pending_capture';
    case PurchaseReleased = 'purchase.released';
    case PurchasePendingRelease = 'purchase.pending_release';
    case PurchasePreauthorized = 'purchase.preauthorized';
    case PurchaseRecurringTokenDeleted = 'purchase.recurring_token_deleted';
    case PurchasePendingRecurringTokenDelete = 'purchase.pending_recurring_token_delete';
    case PurchasePendingRefund = 'purchase.pending_refund';

    // Payment events
    case PaymentRefunded = 'payment.refunded';
    case PaymentChargedBack = 'payment.charged_back';
    case PaymentChargebackReversed = 'payment.chargeback_reversed';

    // Remaining purchase events
    case PurchaseViewed = 'purchase.viewed';
    case PurchaseSettled = 'purchase.settled';

    // Payout events
    case PayoutCreated = 'payout.created';
    case PayoutPending = 'payout.pending';
    case PayoutFailed = 'payout.failed';
    case PayoutSuccess = 'payout.success';

    /**
     * Create enum from string value.
     */
    public static function fromString(string $eventType): ?self
    {
        return self::tryFrom($eventType);
    }

    /**
     * Get human-readable label for the event type.
     */
    public function label(): string
    {
        return match ($this) {
            self::PurchaseCreated => 'Purchase Created',
            self::PurchasePaid => 'Purchase Paid',
            self::PurchasePaymentFailure => 'Payment Failure',
            self::PurchaseRefundFailure => 'Refund Failure',
            self::PurchaseCaptureFailure => 'Capture Failure',
            self::PurchaseReleaseFailure => 'Release Failure',
            self::PurchasePendingExecute => 'Pending Execution',
            self::PurchasePendingCharge => 'Pending Charge',
            self::PurchaseCancelled => 'Purchase Cancelled',
            self::PurchaseHold => 'Funds On Hold',
            self::PurchaseCaptured => 'Payment Captured',
            self::PurchasePendingCapture => 'Pending Capture',
            self::PurchaseReleased => 'Funds Released',
            self::PurchasePendingRelease => 'Pending Release',
            self::PurchasePreauthorized => 'Card Preauthorized',
            self::PurchaseRecurringTokenDeleted => 'Recurring Token Deleted',
            self::PurchasePendingRecurringTokenDelete => 'Pending Token Deletion',
            self::PurchasePendingRefund => 'Pending Refund',
            self::PaymentRefunded => 'Payment Refunded',
            self::PaymentChargedBack => 'Payment Charged Back',
            self::PaymentChargebackReversed => 'Payment Chargeback Reversed',
            self::PurchaseViewed => 'Purchase Viewed',
            self::PurchaseSettled => 'Purchase Settled',
            self::PayoutCreated => 'Payout Created',
            self::PayoutPending => 'Payout Pending',
            self::PayoutFailed => 'Payout Failed',
            self::PayoutSuccess => 'Payout Successful',
        };
    }

    /**
     * Check if this is a purchase-related event.
     */
    public function isPurchaseEvent(): bool
    {
        return str_starts_with($this->value, 'purchase.');
    }

    /**
     * Check if this is a payout-related event.
     */
    public function isPayoutEvent(): bool
    {
        return str_starts_with($this->value, 'payout.');
    }

    /**
     * Check if this is a payment-related event.
     */
    public function isPaymentEvent(): bool
    {
        return str_starts_with($this->value, 'payment.');
    }

    /**
     * Check if this is a pending/processing event.
     */
    public function isPendingEvent(): bool
    {
        return str_contains($this->value, 'pending');
    }

    /**
     * Check if this is a success/completion event.
     */
    public function isSuccessEvent(): bool
    {
        return in_array($this, [
            self::PurchasePaid,
            self::PurchaseCaptured,
            self::PurchasePreauthorized,
            self::PurchaseSettled,
            self::PayoutSuccess,
        ], true);
    }

    /**
     * Check if this is a failure event.
     */
    public function isFailureEvent(): bool
    {
        return in_array($this, [
            self::PurchasePaymentFailure,
            self::PurchaseRefundFailure,
            self::PurchaseCaptureFailure,
            self::PurchaseReleaseFailure,
            self::PayoutFailed,
            self::PaymentChargedBack,
        ], true);
    }

    /**
     * Get the corresponding event class name.
     */
    public function eventClass(): string
    {
        $namespace = 'AIArmada\\Chip\\Events\\';

        return match ($this) {
            self::PurchaseCreated => $namespace . 'PurchaseCreated',
            self::PurchasePaid => $namespace . 'PurchasePaid',
            self::PurchasePaymentFailure => $namespace . 'PurchasePaymentFailure',
            self::PurchaseRefundFailure => $namespace . 'PurchaseRefundFailure',
            self::PurchaseCaptureFailure => $namespace . 'PurchaseCaptureFailure',
            self::PurchaseReleaseFailure => $namespace . 'PurchaseReleaseFailure',
            self::PurchasePendingExecute => $namespace . 'PurchasePendingExecute',
            self::PurchasePendingCharge => $namespace . 'PurchasePendingCharge',
            self::PurchaseCancelled => $namespace . 'PurchaseCancelled',
            self::PurchaseHold => $namespace . 'PurchaseHold',
            self::PurchaseCaptured => $namespace . 'PurchaseCaptured',
            self::PurchasePendingCapture => $namespace . 'PurchasePendingCapture',
            self::PurchaseReleased => $namespace . 'PurchaseReleased',
            self::PurchasePendingRelease => $namespace . 'PurchasePendingRelease',
            self::PurchasePreauthorized => $namespace . 'PurchasePreauthorized',
            self::PurchaseRecurringTokenDeleted => $namespace . 'PurchaseRecurringTokenDeleted',
            self::PurchasePendingRecurringTokenDelete => $namespace . 'PurchasePendingRecurringTokenDelete',
            self::PurchasePendingRefund => $namespace . 'PurchasePendingRefund',
            self::PaymentRefunded => $namespace . 'PaymentRefunded',
            self::PaymentChargedBack => $namespace . 'PaymentChargedBack',
            self::PaymentChargebackReversed => $namespace . 'PaymentChargebackReversed',
            self::PurchaseViewed => $namespace . 'PurchaseViewed',
            self::PurchaseSettled => $namespace . 'PurchaseSettled',
            self::PayoutCreated => $namespace . 'PayoutCreated',
            self::PayoutPending => $namespace . 'PayoutPending',
            self::PayoutFailed => $namespace . 'PayoutFailed',
            self::PayoutSuccess => $namespace . 'PayoutSuccess',
        };
    }
}
