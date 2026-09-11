<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Support\Facades\Log;

final readonly class ChipPurchasePayloadBuilder
{
    public function idempotencyKey(CheckoutSession $session): string
    {
        Log::warning('Checkout derived an idempotency key from the session.', [
            'checkout_session_id' => (string) $session->getKey(),
        ]);

        return (string) $session->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CheckoutSession $session, PaymentRequest $request): array
    {
        return [
            'purchase' => [
                'products' => [
                    [
                        'name' => $request->description ?? "Checkout {$session->id}",
                        'price' => $request->amount,
                        'quantity' => 1,
                    ],
                ],
                'currency' => $request->currency,
            ],
            'client' => array_filter([
                'email' => $request->customerEmail,
                'full_name' => $request->customerName,
                'phone' => $request->customerPhone,
            ], static fn (mixed $value): bool => $value !== null
                && (! is_string($value) || mb_trim($value) !== '')),
            'reference' => CheckoutPaymentReference::forSession($session),
            'idempotency_key' => $this->idempotencyKey($session),
            'success_redirect' => $request->successUrl,
            'failure_redirect' => $request->failureUrl,
            'cancel_redirect' => $request->cancelUrl,
        ];
    }
}
