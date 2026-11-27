<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\CommerceSupport\Events\PaymentRefunded;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Platform\CommerceIntegration;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Completes an event registration refund after an asynchronous provider webhook confirms it.
 */
final readonly class CompletePendingRefundOnPaymentRefundedAction
{
    public function __construct(
        private EventOrderDriver $orders,
    ) {}

    public function handle(PaymentRefunded $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        if ($event->amount <= 0 || mb_trim($event->provider) === '') {
            return;
        }

        $registrationId = data_get($event->metadata, 'event_registration_id');
        $registrationId = is_scalar($registrationId) && mb_trim((string) $registrationId) !== ''
            ? (string) $registrationId
            : null;

        $match = $this->orders->matchProviderRefundConfirmation(
            provider: mb_strtolower(mb_trim($event->provider)),
            providerRefundId: $event->paymentId,
            relatedPaymentId: $event->relatedPaymentId,
            amount: $event->amount,
            currency: $event->currency,
            eventRegistrationId: $registrationId,
        );

        if ($match->matchedCount === 0) {
            return;
        }

        if ($match->matchedCount > 1) {
            $this->warnAmbiguous($event, $match->matchedCount);

            return;
        }

        if ($match->refundId === null || ! $match->pending) {
            return;
        }

        $refundId = $match->refundId;

        try {
            $owner = OwnerContext::fromTypeAndIdOrFail($match->ownerType, $match->ownerId);

            OwnerContext::withOwner($owner, function () use ($event, $refundId): void {
                if ($this->orders->correlateRefundProviderReference($refundId, $event->paymentId, $event->relatedPaymentId)) {
                    $this->orders->completeRefund($refundId, $event->paymentId ?? $event->reference);
                }
            });
        } catch (Throwable $exception) {
            Log::error('Remote payment refund was confirmed but could not be recorded locally', [
                'provider' => $event->provider,
                'payment_id' => $event->paymentId,
                'related_payment_id' => $event->relatedPaymentId,
                'refund_id' => $match->refundId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function warnAmbiguous(PaymentRefunded $event, int $count): void
    {
        Log::warning('Remote payment refund could not be matched unambiguously to an event refund', [
            'provider' => $event->provider,
            'payment_id' => $event->paymentId,
            'related_payment_id' => $event->relatedPaymentId,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'pending_refund_count' => $count,
        ]);
    }
}
