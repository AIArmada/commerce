<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Actions\Purchases\SyncPurchaseRefundState;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PaymentChargebackReversed;
use AIArmada\Chip\Events\PaymentChargedBack;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PayoutCreated;
use AIArmada\Chip\Events\PayoutFailed;
use AIArmada\Chip\Events\PayoutPending;
use AIArmada\Chip\Events\PayoutSuccess;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchaseCaptured;
use AIArmada\Chip\Events\PurchaseCaptureFailure;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePendingCapture;
use AIArmada\Chip\Events\PurchasePendingCharge;
use AIArmada\Chip\Events\PurchasePendingExecute;
use AIArmada\Chip\Events\PurchasePendingRecurringTokenDelete;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\PurchasePendingRelease;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseRecurringTokenDeleted;
use AIArmada\Chip\Events\PurchaseRefundFailure;
use AIArmada\Chip\Events\PurchaseReleased;
use AIArmada\Chip\Events\PurchaseReleaseFailure;
use AIArmada\Chip\Events\PurchaseSettled;
use AIArmada\Chip\Events\PurchaseViewed;
use AIArmada\CommerceSupport\Events\PaymentRefunded as CommercePaymentRefunded;
use Illuminate\Support\Facades\Log;

/**
 * Centralized webhook event dispatcher.
 *
 * This service consolidates webhook event dispatching logic to avoid duplication
 * between WebhookController (HTTP) and ProcessChipWebhook (queue job).
 */
class WebhookEventDispatcher
{
    private readonly SyncPurchaseRefundState $syncPurchaseRefundState;

    public function __construct(?SyncPurchaseRefundState $syncPurchaseRefundState = null)
    {
        $this->syncPurchaseRefundState = $syncPurchaseRefundState ?? app(SyncPurchaseRefundState::class);
    }

    /**
     * Dispatch the appropriate typed event based on event type.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $eventType, array $payload): void
    {
        $type = WebhookEventType::fromString($eventType);

        if ($type === null) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('Unknown CHIP webhook event type', [
                    'event_type' => $eventType,
                ]);

            return;
        }

        match ($type) {
            // Purchase lifecycle events
            WebhookEventType::PurchaseCreated => PurchaseCreated::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePaid => PurchasePaid::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePaymentFailure => PurchasePaymentFailure::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseRefundFailure => PurchaseRefundFailure::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCaptureFailure => PurchaseCaptureFailure::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseReleaseFailure => PurchaseReleaseFailure::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCancelled => PurchaseCancelled::dispatch($this->extractPurchase($payload), $payload),

            // Pending events
            WebhookEventType::PurchasePendingExecute => PurchasePendingExecute::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingCharge => PurchasePendingCharge::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingCapture => PurchasePendingCapture::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRelease => PurchasePendingRelease::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRefund => PurchasePendingRefund::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRecurringTokenDelete => PurchasePendingRecurringTokenDelete::dispatch($this->extractPurchase($payload), $payload),

            // Authorization/capture events
            WebhookEventType::PurchaseHold => PurchaseHold::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCaptured => PurchaseCaptured::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseReleased => PurchaseReleased::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePreauthorized => PurchasePreauthorized::dispatch($this->extractPurchase($payload), $payload),

            // Recurring token events
            WebhookEventType::PurchaseRecurringTokenDeleted => PurchaseRecurringTokenDeleted::dispatch($this->extractPurchase($payload), $payload),

            // Additional purchase events
            WebhookEventType::PurchaseViewed => PurchaseViewed::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseSettled => PurchaseSettled::dispatch($this->extractPurchase($payload), $payload),

            // Refund events
            WebhookEventType::PaymentRefunded => $this->dispatchPaymentRefunded($payload),
            WebhookEventType::PaymentChargedBack => PaymentChargedBack::dispatch($this->extractPayment($payload), $payload),
            WebhookEventType::PaymentChargebackReversed => PaymentChargebackReversed::dispatch($this->extractPayment($payload), $payload),

            // Payout events
            WebhookEventType::PayoutCreated => PayoutCreated::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutPending => PayoutPending::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutFailed => PayoutFailed::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutSuccess => PayoutSuccess::dispatch($this->extractPayout($payload), $payload),
        };
    }

    /**
     * Extract Purchase data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPurchase(array $payload): ?PurchaseData
    {
        $type = $payload['type'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($type === 'purchase' && WebhookEventType::fromString($eventType)?->isPurchaseEvent()) {
            return PurchaseData::from($payload);
        }

        return null;
    }

    /**
     * Extract Payment data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?PaymentData
    {
        $type = $payload['type'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($type === 'payment' && WebhookEventType::fromString($eventType)?->isPaymentEvent()) {
            return PaymentData::fromWebhookPayload($payload);
        }

        return null;
    }

    /**
     * Extract Payout data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractPayout(array $payload): ?PayoutData
    {
        $type = $payload['type'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($type === 'payout' && WebhookEventType::fromString($eventType)?->isPayoutEvent()) {
            return PayoutData::from($payload);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchPaymentRefunded(array $payload): void
    {
        $payment = $this->extractPayment($payload);

        if ($payment === null) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('Invalid CHIP refund payment payload', [
                    'event_type' => $payload['event_type'] ?? 'payment.refunded',
                    'id' => $payload['id'] ?? null,
                ]);

            return;
        }

        $this->syncPurchaseRefundState->handle($payment);

        PaymentRefunded::dispatch($payment, $payload);

        CommercePaymentRefunded::dispatch(
            provider: 'chip',
            paymentId: $payment->getPaymentId() ?? (is_string($payload['id'] ?? null) ? $payload['id'] : null),
            relatedPaymentId: $payment->getRelatedPurchaseId(),
            amount: $payment->getAmountInCents(),
            currency: $payment->getCurrency(),
            reference: $payment->getReference(),
            metadata: [],
            payload: $payload,
        );
    }
}
