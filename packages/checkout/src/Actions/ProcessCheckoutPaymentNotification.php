<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

final class ProcessCheckoutPaymentNotification
{
    use AsAction;

    public function __construct(
        private readonly CheckoutServiceInterface $checkoutService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $expectedGateways
     */
    public function handle(array $payload, ?string $callbackType = null, array $context = [], array $expectedGateways = []): void
    {
        $sessionId = $this->extractSessionId($payload);

        if ($sessionId === null) {
            Log::warning('Checkout payment notification missing session reference', $this->logContext($context));

            return;
        }

        $callbackType ??= $this->resolveCallbackType($payload);

        if ($callbackType === null) {
            return;
        }

        DB::transaction(function () use ($callbackType, $context, $expectedGateways, $payload, $sessionId): void {
            $session = CheckoutSession::withoutOwnerScope()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                Log::warning('Checkout payment notification session not found', $this->logContext($context, [
                    'session_id' => $sessionId,
                ]));

                return;
            }

            if ($session->status instanceof Completed) {
                return;
            }

            if ($expectedGateways !== [] && ! in_array((string) $session->selected_payment_gateway, $expectedGateways, true)) {
                Log::info('Checkout payment notification ignored for unexpected payment gateway', $this->logContext($context, [
                    'session_id' => $sessionId,
                    'selected_payment_gateway' => $session->selected_payment_gateway,
                    'expected_gateways' => $expectedGateways,
                ]));

                return;
            }

            if (! $this->canHandleCallback($session, $callbackType)) {
                Log::info('Checkout payment notification ignored for session in unexpected state', $this->logContext($context, [
                    'session_id' => $sessionId,
                    'status' => $session->status->name(),
                ]));

                return;
            }

            $this->checkoutService->handlePaymentCallback($session, $callbackType, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSessionId(array $payload): ?string
    {
        $reference = Arr::get($payload, 'reference');

        if (is_string($reference) && $reference !== '') {
            return $reference;
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
    private function resolveCallbackType(array $payload): ?string
    {
        return match ($this->extractPaymentStatus($payload)) {
            PaymentStatus::Completed => 'success',
            PaymentStatus::Failed => 'failure',
            PaymentStatus::Cancelled => 'cancel',
            default => null,
        };
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

    private function canHandleCallback(CheckoutSession $session, string $callbackType): bool
    {
        $isInPaymentState = $session->status instanceof AwaitingPayment
            || $session->status instanceof PaymentProcessing
            || $session->status instanceof Processing;

        if ($callbackType === 'success') {
            return $isInPaymentState;
        }

        if ($callbackType === 'failure' || $callbackType === 'cancel') {
            return $isInPaymentState || $session->status instanceof Pending;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function logContext(array $context, array $extra = []): array
    {
        $mergedContext = array_merge([
            'source' => 'checkout.payment_notification',
        ], $context, $extra);

        return array_filter($mergedContext, static fn (mixed $value): bool => $value !== null);
    }
}
