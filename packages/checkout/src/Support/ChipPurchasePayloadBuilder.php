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
        // Prefer the key frozen for the claimed attempt so a crashed or
        // retried attempt always reconciles the same external charge.
        $frozen = data_get($session->payment_data ?? [], 'payment_attempt.idempotency_key');

        if (is_string($frozen) && $frozen !== '') {
            return $frozen;
        }

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
        $purchase = [
            'products' => [
                [
                    'name' => $request->description ?? "Checkout {$session->id}",
                    'price' => $request->amount,
                    'quantity' => 1,
                ],
            ],
            'currency' => $request->currency,
        ];

        // CHIP echoes purchase metadata on webhooks. Persist the attempt
        // generation and gateway with the purchase so callbacks that arrive
        // before the create response can still be bound to the attempt that
        // owns them instead of relying on a session-level reference.
        $metadata = array_filter(
            $request->metadata,
            static fn (mixed $value): bool => $value !== null
                && (! is_string($value) || mb_trim($value) !== ''),
        );

        if ($metadata !== []) {
            $purchase['metadata'] = $metadata;
        }

        return [
            'purchase' => $purchase,
            'client' => array_filter(
                $request->clientFrozen
                    ? $this->frozenClient($request)
                    : $this->liveClient($session, $request),
                static fn (mixed $value): bool => $value !== null
                    && (! is_string($value) || mb_trim($value) !== ''),
            ),
            'reference' => CheckoutPaymentReference::forSession($session),
            'idempotency_key' => $this->idempotencyKey($session),
            'success_redirect' => $request->successUrl,
            'failure_redirect' => $request->failureUrl,
            'cancel_redirect' => $request->cancelUrl,
        ];
    }

    /**
     * The client frozen into the attempt claim. Replay must never read
     * mutable session billing state, and a frozen null is an explicit
     * provider payload omission rather than a gap to backfill.
     *
     * @return array<string, mixed>
     */
    private function frozenClient(PaymentRequest $request): array
    {
        return [
            'email' => self::clientString($request->client, 'email') ?? $request->customerEmail,
            'full_name' => self::clientString($request->client, 'full_name') ?? $request->customerName,
            'phone' => self::clientString($request->client, 'phone') ?? $request->customerPhone,
            'street_address' => self::clientString($request->client, 'street_address'),
            'city' => self::clientString($request->client, 'city'),
            'state' => self::clientString($request->client, 'state'),
            'zip_code' => self::clientString($request->client, 'zip_code'),
            'country' => self::clientString($request->client, 'country'),
        ];
    }

    /**
     * Legacy direct-request path for hosts that build a PaymentRequest
     * themselves rather than replaying a claimed attempt.
     *
     * @return array<string, mixed>
     */
    private function liveClient(CheckoutSession $session, PaymentRequest $request): array
    {
        $billingData = $session->billing_data ?? [];

        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $client
     */
    private static function clientString(array $client, string $key): ?string
    {
        $value = $client[$key] ?? null;

        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return mb_trim($value);
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
