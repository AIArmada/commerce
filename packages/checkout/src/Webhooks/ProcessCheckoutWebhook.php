<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Webhooks;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessCheckoutWebhook extends CommerceWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $sessionId = $this->extractSessionId($payload);

        if ($sessionId === null) {
            Log::warning('Checkout webhook missing session reference', [
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        DB::transaction(function () use ($payload, $sessionId): void {
            $session = CheckoutSession::withoutOwnerScope()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                Log::warning('Checkout webhook session not found', [
                    'session_id' => $sessionId,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

                return;
            }

            if ($session->status instanceof Completed) {
                return;
            }

            $paymentStatus = $this->extractPaymentStatus($payload);

            $isInPaymentState = $session->status instanceof AwaitingPayment
                || $session->status instanceof PaymentProcessing
                || $session->status instanceof Processing;

            $canCancelFromPending = $session->status instanceof Pending
                && in_array($paymentStatus, [PaymentStatus::Failed, PaymentStatus::Cancelled], true);

            if (! $isInPaymentState && ! $canCancelFromPending) {
                Log::info('Checkout webhook ignored for session in unexpected state', [
                    'session_id' => $sessionId,
                    'status' => $session->status->name(),
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

                return;
            }

            $callbackType = match ($paymentStatus) {
                PaymentStatus::Completed => 'success',
                PaymentStatus::Failed => 'failure',
                PaymentStatus::Cancelled => 'cancel',
                default => null,
            };

            if ($callbackType === null) {
                return;
            }

            /** @var CheckoutServiceInterface $checkoutService */
            $checkoutService = app(CheckoutServiceInterface::class);
            $checkoutService->handlePaymentCallback($session, $callbackType, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventType(array $payload): string
    {
        return (string) (
            Arr::get($payload, 'event_type')
            ?? Arr::get($payload, 'event')
            ?? Arr::get($payload, 'type')
            ?? 'unknown'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSessionId(array $payload): ?string
    {
        if (isset($payload['reference']) && is_string($payload['reference'])) {
            return $payload['reference'];
        }

        $metadataSessionId = Arr::get($payload, 'metadata.checkout_session_id');
        if (is_string($metadataSessionId) && $metadataSessionId !== '') {
            return $metadataSessionId;
        }

        $objectMetadataSessionId = Arr::get($payload, 'data.object.metadata.checkout_session_id');
        if (is_string($objectMetadataSessionId) && $objectMetadataSessionId !== '') {
            return $objectMetadataSessionId;
        }

        $clientReferenceId = Arr::get($payload, 'data.object.client_reference_id');
        if (is_string($clientReferenceId) && $clientReferenceId !== '') {
            return $clientReferenceId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentStatus(array $payload): PaymentStatus
    {
        $status = Arr::get($payload, 'status') ?? Arr::get($payload, 'data.object.status');

        return match ($status) {
            'paid', 'completed', 'succeeded', 'complete' => PaymentStatus::Completed,
            'pending', 'created', 'processing' => PaymentStatus::Pending,
            'failed', 'error', 'payment_failed' => PaymentStatus::Failed,
            'cancelled', 'canceled', 'expired' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };
    }
}
