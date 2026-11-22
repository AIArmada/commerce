<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Events\PaymentRefunded;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\OrderRefund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Completes an event registration refund after an asynchronous provider webhook confirms it.
 */
final readonly class CompletePendingRefundOnPaymentRefunded
{
    public function __construct(
        private ?OrderServiceInterface $orders = null,
    ) {}

    public function handle(PaymentRefunded $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        if ($event->amount <= 0 || mb_trim($event->provider) === '') {
            return;
        }

        $provider = mb_strtolower(mb_trim($event->provider));
        $paymentId = $event->paymentId !== null ? mb_trim($event->paymentId) : null;

        $baseQuery = $this->refundQuery($event);

        if ($paymentId !== null && $paymentId !== '') {
            $refunds = $this->refundQuery($event, includeCompleted: true)
                ->where('metadata->provider_refund_id', $paymentId)
                ->where('metadata->provider', $provider)
                ->get();

            if ($refunds->count() === 1) {
                $refund = $refunds->first();

                if ($refund instanceof OrderRefund && $refund->isPending()) {
                    $this->complete($refund, $event);
                }

                return;
            }

            if ($refunds->count() > 1) {
                $this->warnAmbiguous($event, $refunds->count());

                return;
            }
        }

        $registrationId = data_get($event->metadata, 'event_registration_id');

        if (is_scalar($registrationId) && mb_trim((string) $registrationId) !== '') {
            $refunds = $this->refundQuery($event, includeCompleted: true)
                ->where('metadata->event_registration_id', (string) $registrationId)
                ->get();

            if ($refunds->count() === 1) {
                $refund = $refunds->first();

                if ($refund instanceof OrderRefund && $refund->isPending()) {
                    $this->recordProviderCorrelation($refund, $paymentId, $event->relatedPaymentId);
                    $this->complete($refund, $event);
                }

                return;
            }

            if ($refunds->count() > 1) {
                $this->warnAmbiguous($event, $refunds->count());

                return;
            }
        }

        if ($event->relatedPaymentId === null || mb_trim($event->relatedPaymentId) === '') {
            return;
        }

        $refunds = $baseQuery
            ->where('metadata->payment_id', $event->relatedPaymentId)
            ->where('metadata->provider', $provider)
            ->get();

        if ($refunds->count() !== 1) {
            if ($refunds->isNotEmpty()) {
                $this->warnAmbiguous($event, $refunds->count());
            }

            return;
        }

        $refund = $refunds->first();

        if (! $refund instanceof OrderRefund) {
            return;
        }

        if (! $this->recordProviderCorrelation($refund, $paymentId, $event->relatedPaymentId)) {
            return;
        }

        $this->complete($refund, $event);
    }

    /**
     * @return Builder<OrderRefund>
     */
    private function refundQuery(PaymentRefunded $event, bool $includeCompleted = false): Builder
    {
        $statuses = $includeCompleted
            ? [RefundStatus::Pending, RefundStatus::Completed]
            : [RefundStatus::Pending];

        return OrderRefund::query()
            ->with(['order', 'payment'])
            ->whereIn('status', $statuses)
            ->where('amount', $event->amount)
            ->where('currency', mb_strtoupper($event->currency))
            ->whereNotNull('metadata->event_registration_id');
    }

    private function recordProviderCorrelation(
        OrderRefund $refund,
        ?string $paymentId,
        ?string $relatedPaymentId,
    ): bool {
        if (in_array($paymentId, [null, '', $relatedPaymentId], true)) {
            return true;
        }

        $metadata = is_array($refund->metadata) ? $refund->metadata : [];
        $existingId = data_get($metadata, 'provider_refund_id');

        if (is_scalar($existingId) && mb_trim((string) $existingId) !== '') {
            return mb_trim((string) $existingId) === $paymentId;
        }

        $metadata['provider_refund_id'] = $paymentId;
        $refund->forceFill(['metadata' => $metadata])->save();

        return true;
    }

    private function complete(mixed $refund, PaymentRefunded $event): void
    {
        if (! $refund instanceof OrderRefund) {
            return;
        }

        $orders = $this->orders ?? app(OrderServiceInterface::class);

        try {
            OwnerContext::withOwner($refund->order->owner ?? null, static function () use ($orders, $refund, $event): void {
                $orders->completePendingRefund(
                    $refund,
                    $event->paymentId ?? $event->reference,
                );
            });
        } catch (Throwable $exception) {
            Log::error('Remote payment refund was confirmed but could not be recorded locally', [
                'provider' => $event->provider,
                'payment_id' => $event->paymentId,
                'related_payment_id' => $event->relatedPaymentId,
                'refund_id' => $refund->getKey(),
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
