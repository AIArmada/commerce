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

        // The first attempt keeps the bare session key. Retries must not
        // reuse it: CHIP would return the original (possibly stale-amount)
        // purchase instead of creating a fresh one.
        $attempts = (int) ($session->payment_attempts ?? 0);

        if ($attempts <= 0) {
            return (string) $session->getKey();
        }

        return (string) $session->getKey() . ':attempt:' . $attempts;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CheckoutSession $session, PaymentRequest $request): array
    {
        $billingData = $session->billing_data ?? [];

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
                'street_address' => self::streetAddress($billingData),
                'city' => $billingData['city'] ?? null,
                'state' => $billingData['state'] ?? null,
                'zip_code' => $billingData['postcode'] ?? null,
                // ISO 3166-1 alpha-2 code: the sandbox accepts the full country name too,
                // but the code is the documented/choice-field-safe form.
                'country' => $billingData['country_code'] ?? null,
            ], static fn (mixed $value): bool => $value !== null
                && (! is_string($value) || mb_trim($value) !== '')),
            'reference' => CheckoutPaymentReference::forSession($session),
            'idempotency_key' => $this->idempotencyKey($session),
            'success_redirect' => $request->successUrl,
            'failure_redirect' => $request->failureUrl,
            'cancel_redirect' => $request->cancelUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $billingData
     */
    private static function streetAddress(array $billingData): ?string
    {
        $lines = [];

        foreach (['line1', 'line2', 'line3'] as $key) {
            $line = $billingData[$key] ?? null;

            if (is_string($line) && mb_trim($line) !== '') {
                $lines[] = mb_trim($line);
            }
        }

        return $lines === [] ? null : implode(', ', $lines);
    }
}
