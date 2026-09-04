<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a CHIP webhook is received.
 *
 * This is the generic webhook event dispatched for all incoming webhooks.
 * Specific typed events (PurchasePaid, PayoutSuccess, etc.) are also dispatched.
 */
class WebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventType,
        public readonly array $payload,
        public readonly ?PurchaseData $purchase = null,
        public readonly ?PayoutData $payout = null,
        public readonly ?PaymentData $payment = null,
    ) {}

    /**
     * Create event from a raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $eventType = $payload['event_type'] ?? 'unknown';
        $purchase = null;
        $payout = null;
        $payment = null;

        $type = $payload['type'] ?? '';

        if ($type === 'payment') {
            $payment = PaymentData::fromWebhookPayload($payload);
        } elseif ($type === 'purchase') {
            $purchase = PurchaseData::from($payload);
        } elseif ($type === 'payout') {
            $payout = PayoutData::from($payload);
        }

        return new self(
            eventType: $eventType,
            payload: $payload,
            purchase: $purchase,
            payout: $payout,
            payment: $payment,
        );
    }

    /**
     * Get the webhook event type enum if valid.
     */
    public function getEventTypeEnum(): ?WebhookEventType
    {
        return WebhookEventType::fromString($this->eventType);
    }

    // Purchase lifecycle events

    public function isCreated(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCreated->value;
    }

    public function isPaid(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePaid->value;
    }

    public function isPaymentFailure(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePaymentFailure->value;
    }

    public function isRefundFailure(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseRefundFailure->value;
    }

    public function isCaptureFailure(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCaptureFailure->value;
    }

    public function isReleaseFailure(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseReleaseFailure->value;
    }

    public function isCancelled(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCancelled->value;
    }

    // Pending events

    public function isPendingExecute(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingExecute->value;
    }

    public function isPendingCharge(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingCharge->value;
    }

    public function isPendingCapture(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingCapture->value;
    }

    public function isPendingRelease(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingRelease->value;
    }

    public function isPendingRefund(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingRefund->value;
    }

    public function isPendingRecurringTokenDelete(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingRecurringTokenDelete->value;
    }

    // Authorization/capture events

    public function isHold(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseHold->value;
    }

    public function isCaptured(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCaptured->value;
    }

    public function isReleased(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseReleased->value;
    }

    public function isPreauthorized(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePreauthorized->value;
    }

    public function isViewed(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseViewed->value;
    }

    public function isSettled(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseSettled->value;
    }

    // Recurring token events

    public function isRecurringTokenDeleted(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseRecurringTokenDeleted->value;
    }

    // Refund events

    public function isRefunded(): bool
    {
        return $this->eventType === WebhookEventType::PaymentRefunded->value;
    }

    public function isChargedBack(): bool
    {
        return $this->eventType === WebhookEventType::PaymentChargedBack->value;
    }

    public function isChargebackReversed(): bool
    {
        return $this->eventType === WebhookEventType::PaymentChargebackReversed->value;
    }

    // Payout events

    public function isPayoutPending(): bool
    {
        return $this->eventType === WebhookEventType::PayoutPending->value;
    }

    public function isPayoutFailed(): bool
    {
        return $this->eventType === WebhookEventType::PayoutFailed->value;
    }

    public function isPayoutSuccess(): bool
    {
        return $this->eventType === WebhookEventType::PayoutSuccess->value;
    }

    // Category checks

    public function isPurchaseEvent(): bool
    {
        return $this->getEventTypeEnum()?->isPurchaseEvent() ?? false;
    }

    public function isPayoutEvent(): bool
    {
        return $this->getEventTypeEnum()?->isPayoutEvent() ?? false;
    }

    public function isPaymentEvent(): bool
    {
        return $this->getEventTypeEnum()?->isPaymentEvent() ?? false;
    }

    public function isPendingEvent(): bool
    {
        return $this->getEventTypeEnum()?->isPendingEvent() ?? false;
    }

    public function isSuccessEvent(): bool
    {
        return $this->getEventTypeEnum()?->isSuccessEvent() ?? false;
    }

    public function isFailureEvent(): bool
    {
        return $this->getEventTypeEnum()?->isFailureEvent() ?? false;
    }

    // Data accessors

    public function getReference(): ?string
    {
        return $this->payment?->getReference() ?? $this->payload['reference'] ?? null;
    }

    public function getPurchaseId(): ?string
    {
        if ($this->payment !== null) {
            return $this->payment->getRelatedPurchaseId();
        }

        if ($this->isPaymentEvent()) {
            $relatedPurchaseId = data_get($this->payload, 'related_to.type') === 'purchase'
                ? data_get($this->payload, 'related_to.id')
                : null;

            return is_string($relatedPurchaseId) && $relatedPurchaseId !== ''
                ? $relatedPurchaseId
                : null;
        }

        $purchaseId = $this->payload['id'] ?? null;

        return is_string($purchaseId) && $purchaseId !== ''
            ? $purchaseId
            : null;
    }

    public function getClientId(): ?string
    {
        return $this->payload['client_id'] ?? null;
    }

    /**
     * Get the amount in cents.
     */
    public function getAmount(): int
    {
        return $this->payment?->getAmountInCents()
            ?? (is_numeric(data_get($this->payload, 'purchase.total'))
                ? (int) data_get($this->payload, 'purchase.total')
                : 0);
    }

    public function getCurrency(): string
    {
        return $this->payment?->getCurrency()
            ?? (is_string(data_get($this->payload, 'purchase.currency'))
                ? data_get($this->payload, 'purchase.currency')
                : (string) config('chip.defaults.currency', 'MYR'));
    }

    /**
     * Check if this is a test webhook.
     */
    public function isTest(): bool
    {
        return (bool) ($this->payload['is_test'] ?? false);
    }
}
