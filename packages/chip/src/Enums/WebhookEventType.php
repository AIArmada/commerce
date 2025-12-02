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
    // Purchase lifecycle events
    case PurchaseCreated = 'purchase.created';
    case PurchasePaid = 'purchase.paid';
    case PurchasePaymentFailure = 'purchase.payment_failure';
    case PurchaseCancelled = 'purchase.cancelled';

    // Pending transaction events
    case PurchasePendingExecute = 'purchase.pending_execute';
    case PurchasePendingCharge = 'purchase.pending_charge';

    // Authorization/capture events
    case PurchaseHold = 'purchase.hold';
    case PurchaseCaptured = 'purchase.captured';
    case PurchasePendingCapture = 'purchase.pending_capture';
    case PurchaseReleased = 'purchase.released';
    case PurchasePendingRelease = 'purchase.pending_release';
    case PurchasePreauthorized = 'purchase.preauthorized';

    // Recurring token events
    case PurchaseRecurringTokenDeleted = 'purchase.recurring_token_deleted';
    case PurchasePendingRecurringTokenDelete = 'purchase.pending_recurring_token_delete';

    // Subscription events
    case PurchaseSubscriptionChargeFailure = 'purchase.subscription_charge_failure';

    // Refund events
    case PurchasePendingRefund = 'purchase.pending_refund';
    case PaymentRefunded = 'payment.refunded';

    // Billing template events
    case BillingTemplateClientSubscriptionBillingCancelled = 'billing_template_client.subscription_billing_cancelled';

    // Payout events
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
            self::PurchaseCancelled => 'Purchase Cancelled',
            self::PurchasePendingExecute => 'Pending Execution',
            self::PurchasePendingCharge => 'Pending Charge',
            self::PurchaseHold => 'Funds On Hold',
            self::PurchaseCaptured => 'Payment Captured',
            self::PurchasePendingCapture => 'Pending Capture',
            self::PurchaseReleased => 'Funds Released',
            self::PurchasePendingRelease => 'Pending Release',
            self::PurchasePreauthorized => 'Card Preauthorized',
            self::PurchaseRecurringTokenDeleted => 'Recurring Token Deleted',
            self::PurchasePendingRecurringTokenDelete => 'Pending Token Deletion',
            self::PurchaseSubscriptionChargeFailure => 'Subscription Charge Failed',
            self::PurchasePendingRefund => 'Pending Refund',
            self::PaymentRefunded => 'Payment Refunded',
            self::BillingTemplateClientSubscriptionBillingCancelled => 'Subscription Billing Cancelled',
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
     * Check if this is a billing-related event.
     */
    public function isBillingEvent(): bool
    {
        return str_starts_with($this->value, 'billing_template_client.');
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
            self::PurchaseReleased,
            self::PurchasePreauthorized,
            self::PayoutSuccess,
        ]);
    }

    /**
     * Check if this is a failure event.
     */
    public function isFailureEvent(): bool
    {
        return in_array($this, [
            self::PurchasePaymentFailure,
            self::PurchaseSubscriptionChargeFailure,
            self::PayoutFailed,
        ]);
    }

    /**
     * Get the corresponding event class name.
     */
    public function eventClass(): string
    {
        $namespace = 'AIArmada\\Chip\\Events\\';

        return match ($this) {
            self::PurchaseCreated => $namespace.'PurchaseCreated',
            self::PurchasePaid => $namespace.'PurchasePaid',
            self::PurchasePaymentFailure => $namespace.'PurchasePaymentFailure',
            self::PurchaseCancelled => $namespace.'PurchaseCancelled',
            self::PurchasePendingExecute => $namespace.'PurchasePendingExecute',
            self::PurchasePendingCharge => $namespace.'PurchasePendingCharge',
            self::PurchaseHold => $namespace.'PurchaseHold',
            self::PurchaseCaptured => $namespace.'PurchaseCaptured',
            self::PurchasePendingCapture => $namespace.'PurchasePendingCapture',
            self::PurchaseReleased => $namespace.'PurchaseReleased',
            self::PurchasePendingRelease => $namespace.'PurchasePendingRelease',
            self::PurchasePreauthorized => $namespace.'PurchasePreauthorized',
            self::PurchaseRecurringTokenDeleted => $namespace.'PurchaseRecurringTokenDeleted',
            self::PurchasePendingRecurringTokenDelete => $namespace.'PurchasePendingRecurringTokenDelete',
            self::PurchaseSubscriptionChargeFailure => $namespace.'PurchaseSubscriptionChargeFailure',
            self::PurchasePendingRefund => $namespace.'PurchasePendingRefund',
            self::PaymentRefunded => $namespace.'PaymentRefunded',
            self::BillingTemplateClientSubscriptionBillingCancelled => $namespace.'BillingCancelled',
            self::PayoutPending => $namespace.'PayoutPending',
            self::PayoutFailed => $namespace.'PayoutFailed',
            self::PayoutSuccess => $namespace.'PayoutSuccess',
        };
    }
}
