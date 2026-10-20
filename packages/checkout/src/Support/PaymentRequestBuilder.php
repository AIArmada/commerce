<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;

/**
 * Builds the complete provider-relevant creation request exactly once, at
 * the moment a new attempt generation is claimed.
 *
 * Every later replay reads the frozen snapshot instead of re-deriving
 * customer, billing, metadata, or callback values from mutable session state.
 */
final class PaymentRequestBuilder
{
    public function build(CheckoutSession $session, PaymentAttemptClaim $claim): PaymentRequest
    {
        $billingData = $session->billing_data ?? [];
        $customer = $session->customer;
        $billable = $session->billable;

        // Freeze the authorized concrete model class, not the stored morph
        // alias. A later morph-map change must not redirect the replay to a
        // different customer model.
        $billableType = $billable instanceof Model
            ? $billable::class
            : $this->concreteBillableType($session->billable_type);
        $billableId = $billable instanceof Model
            ? (string) $billable->getKey()
            : (is_string($session->billable_id) ? $session->billable_id : null);

        // Only Eloquent models (or the session's persisted type/id pair) can
        // be resolved by identity on replay. A host-supplied contract object
        // without a local identity must keep using the live session billable;
        // freezing it as an explicit guest would turn a billable charge into
        // a guest charge. The no-billable case is an explicit frozen guest.
        $billableFrozen = $billable instanceof Model
            || $billable === null
            || ($billableType !== null && $billableId !== null);

        $customerName = $this->cleanString($billingData['name'] ?? null);
        if ($customerName === null && $customer !== null) {
            $customerName = $this->cleanString($customer->full_name ?? $customer->first_name ?? null);
        }

        if ($customerName === null && $billable !== null) {
            $customerName = method_exists($billable, 'chipName')
                ? $this->cleanString($billable->chipName())
                : $this->cleanString($billable->name ?? null);
        }

        $customerEmail = $this->cleanString($billingData['email'] ?? null)
            ?? $this->resolveModelEmail($customer);
        if ($customerEmail === null && $billable !== null) {
            $customerEmail = method_exists($billable, 'chipEmail')
                ? $this->cleanString($billable->chipEmail())
                : ($billable instanceof Model ? $this->resolveModelEmail($billable) : null);
        }

        $customerPhone = $this->cleanString($billingData['phone'] ?? null)
            ?? $this->resolveModelPhone($customer);
        if ($customerPhone === null && $billable !== null) {
            $customerPhone = method_exists($billable, 'chipPhone')
                ? $this->cleanString($billable->chipPhone())
                : ($billable instanceof Model ? $this->resolveModelPhone($billable) : null);
        }

        $client = [
            'email' => $customerEmail,
            'full_name' => $customerName,
            'phone' => $customerPhone,
            'street_address' => $this->streetAddress($billingData),
            'city' => $this->cleanString($billingData['city'] ?? null),
            'state' => $this->cleanString($billingData['state'] ?? null),
            'zip_code' => $this->cleanString($billingData['postcode'] ?? null),
            'country' => $this->cleanString($billingData['country_code'] ?? null),
        ];

        return new PaymentRequest(
            amount: $claim->amount,
            currency: (string) $claim->currency,
            gateway: (string) $claim->gateway,
            description: "Order checkout - Session {$session->id}",
            customerEmail: $customerEmail,
            customerName: $customerName,
            customerPhone: $customerPhone,
            successUrl: $this->buildCallbackUrl('success', $session, (string) $claim->gateway),
            failureUrl: $this->buildCallbackUrl('failure', $session, (string) $claim->gateway),
            cancelUrl: $this->buildCallbackUrl('cancel', $session, (string) $claim->gateway),
            metadata: [
                'checkout_session_id' => CheckoutPaymentReference::forSession($session),
                'checkout_gateway' => $claim->gateway,
                'checkout_attempt' => $claim->number,
                'checkout_idempotency_key' => $claim->idempotencyKey,
                ...($claim->provider !== null && $claim->provider !== ''
                    ? ['checkout_provider' => $claim->provider]
                    : []),
                'cart_id' => $session->cart_id,
                'customer_id' => $session->customer_id,
                'billable_type' => $billableType,
                'billable_id' => $billableId,
            ],
            paymentMethod: $claim->paymentMethod,
            provider: $claim->provider,
            idempotencyKey: $claim->idempotencyKey,
            billableType: $billableType,
            billableId: $billableId,
            client: $client,
            clientFrozen: true,
            providerCustomerId: $billableFrozen ? $this->providerCustomerId($billable, $claim) : null,
            providerCustomerFrozen: $billableFrozen,
            billableFrozen: $billableFrozen,
        );
    }

    private function concreteBillableType(mixed $type): ?string
    {
        if (! is_string($type)) {
            return null;
        }

        $type = mb_trim($type);

        if ($type === '') {
            return null;
        }

        return Relation::getMorphedModel($type) ?? $type;
    }

    private function providerCustomerId(?object $billable, PaymentAttemptClaim $claim): ?string
    {
        if ($billable === null) {
            return null;
        }

        $provider = is_string($claim->provider) ? mb_strtolower(mb_trim($claim->provider)) : '';

        if ($provider === '') {
            $provider = match ($claim->gateway) {
                'cashier-chip', 'chip' => 'chip',
                'cashier' => 'stripe',
                default => '',
            };
        }

        $providerId = match ($provider) {
            'stripe' => $this->callModelStringMethod($billable, 'stripeId'),
            'chip' => $this->callModelStringMethod($billable, 'chipId'),
            default => null,
        };

        if ($providerId !== null || $provider !== '') {
            return $providerId;
        }

        return $this->callModelStringMethod($billable, 'stripeId')
            ?? $this->callModelStringMethod($billable, 'chipId');
    }

    private function callModelStringMethod(object $model, string $method): ?string
    {
        if (! method_exists($model, $method)) {
            return null;
        }

        /** @var mixed $value */
        $value = $model->{$method}();

        return $this->cleanString($value);
    }

    /**
     * @param  array<string, mixed>  $billingData
     */
    private function streetAddress(array $billingData): ?string
    {
        $lines = [];

        foreach (['line1', 'line2', 'line3'] as $key) {
            $line = $this->cleanString($billingData[$key] ?? null);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? null : implode(', ', $lines);
    }

    private function buildCallbackUrl(string $type, CheckoutSession $session, string $gateway): string
    {
        $callbackToken = $session->payment_data['callback_token'] ?? null;

        $routeName = match ($type) {
            'success' => "checkout.payment.{$gateway}.success",
            'failure' => "checkout.payment.{$gateway}.failure",
            'cancel' => "checkout.payment.{$gateway}.cancel",
            default => "checkout.payment.{$gateway}.success",
        };

        $queryParam = (string) config('checkout.defaults.session_query_param', 'session');

        if (mb_trim($queryParam) === '') {
            $queryParam = 'session';
        }

        if (Route::has($routeName)) {
            return route($routeName, [
                $queryParam => $session->id,
                'checkout_callback_token' => $callbackToken,
            ]);
        }

        $prefix = mb_trim((string) config('checkout.routes.prefix', 'checkout'), '/');
        $callbackPath = mb_trim((string) config("checkout.routes.callbacks.{$type}.{$gateway}", "payment/{$gateway}/{$type}"), '/');
        $path = mb_trim($prefix . '/' . $callbackPath, '/');
        $separator = str_contains($path, '?') ? '&' : '?';

        return url($path . $separator . http_build_query([
            $queryParam => $session->id,
            'checkout_callback_token' => $callbackToken,
        ]));
    }

    private function resolveModelEmail(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $emailContactMethod = method_exists($model, 'contactMethods')
            ? $model->contactMethods()
                ->where('type', 'email')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first()
            : null;

        return $this->cleanString($emailContactMethod?->normalized_value ?? $emailContactMethod?->value ?? $model->getAttribute('email'));
    }

    private function resolveModelPhone(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $phoneContactMethod = method_exists($model, 'contactMethods')
            ? $model->contactMethods()
                ->where('type', 'phone')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first()
            : null;

        return $this->cleanString($phoneContactMethod?->normalized_value ?? $phoneContactMethod?->value ?? $model->getAttribute('phone'));
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $cleaned = mb_trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
