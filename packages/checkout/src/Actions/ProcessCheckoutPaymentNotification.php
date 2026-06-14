<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CheckoutNotificationCallbackResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class ProcessCheckoutPaymentNotification
{
    use AsAction;

    public function __construct(
        private readonly HandleCheckoutPaymentCallback $handleCallback,
        private readonly CheckoutNotificationCallbackResolver $callbackResolver,
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

        if (! Str::isUuid($sessionId)) {
            Log::warning('Checkout payment notification has invalid session reference format', $this->logContext($context, [
                'session_id' => $sessionId,
            ]));

            return;
        }

        $callbackType ??= $this->callbackResolver->resolveCallbackType($payload);

        if ($callbackType === null) {
            return;
        }

        if ($expectedGateways !== [] && ! $this->gatewayMatches($sessionId, $expectedGateways, $context)) {
            return;
        }

        $this->handleCallback->handle(
            sessionId: $sessionId,
            callbackType: $callbackType,
            payload: $payload,
        );
    }

    /**
     * @param  array<int, string>  $expectedGateways
     * @param  array<string, mixed>  $context
     */
    private function gatewayMatches(string $sessionId, array $expectedGateways, array $context): bool
    {
        $session = CheckoutSession::withoutOwnerScope()
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            Log::warning('Checkout payment notification session not found for gateway check', $this->logContext($context, [
                'session_id' => $sessionId,
            ]));

            return false;
        }

        if (! in_array((string) $session->selected_payment_gateway, $expectedGateways, true)) {
            Log::info('Checkout payment notification ignored for unexpected payment gateway', $this->logContext($context, [
                'session_id' => $sessionId,
                'selected_payment_gateway' => $session->selected_payment_gateway,
                'expected_gateways' => $expectedGateways,
            ]));

            return false;
        }

        return true;
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
