<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CheckoutNotificationCallbackResolver;
use AIArmada\Checkout\Support\CheckoutPaymentReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
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
    public function handle(array $payload, array $expectedGateways, ?string $callbackType = null, array $context = []): void
    {
        if ($expectedGateways === []) {
            Log::warning('Checkout payment notification has no trusted gateway context', $this->logContext($context));

            return;
        }

        $sessionId = $this->extractSessionId($payload, $expectedGateways);

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

        $session = CheckoutSession::withoutOwnerScope()
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            Log::warning('Checkout payment notification session not found', $this->logContext($context, [
                'session_id' => $sessionId,
            ]));

            return;
        }

        OwnerContext::withOwner($session->hasOwner() ? $session->owner : null, function () use ($session, $sessionId, $expectedGateways, $context, $callbackType, $payload): void {
            if (! $this->gatewayMatches($session, $expectedGateways, $context)) {
                return;
            }

            $this->handleCallback->handle(
                sessionId: $sessionId,
                callbackType: $callbackType,
                payload: $payload,
            );
        });
    }

    /**
     * @param  array<int, string>  $expectedGateways
     * @param  array<string, mixed>  $context
     */
    private function gatewayMatches(CheckoutSession $session, array $expectedGateways, array $context): bool
    {
        if (! in_array((string) $session->selected_payment_gateway, $expectedGateways, true)) {
            Log::info('Checkout payment notification ignored for unexpected payment gateway', $this->logContext($context, [
                'session_id' => $session->getKey(),
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
    private function extractSessionId(array $payload, array $expectedGateways): ?string
    {
        $references = [];

        if (array_intersect($expectedGateways, ['chip', 'cashier-chip']) !== []) {
            $references[] = Arr::get($payload, 'reference');
        }

        if (in_array('cashier', $expectedGateways, true)) {
            $references[] = Arr::get($payload, 'metadata.checkout_session_id');
            $references[] = Arr::get($payload, 'data.object.metadata.checkout_session_id');
            $references[] = Arr::get($payload, 'data.object.client_reference_id');
        }

        foreach ($references as $reference) {
            if (! is_string($reference) || $reference === '') {
                continue;
            }

            $sessionId = CheckoutPaymentReference::sessionId($reference);

            if ($sessionId !== null) {
                return $sessionId;
            }
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
