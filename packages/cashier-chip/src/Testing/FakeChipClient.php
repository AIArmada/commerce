<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Testing;

use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Support\TaxPercent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Fake CHIP client for testing CHIP API calls directly.
 *
 * CANONICAL data store for test doubles. Provides low-level mock
 * responses for every CHIP API endpoint using plain arrays.
 *
 * @see FakeChipCollectService wraps this client to provide the
 * same interface as the real ChipCollectService with typed DTOs.
 */
class FakeChipClient
{
    protected array $clients = [];

    protected array $purchases = [];

    protected array $purchasesByKey = [];

    protected array $purchaseFingerprintsByKey = [];

    protected bool $pendingRefundOnce = false;

    protected array $recurringTokens = [];

    protected array $webhooks = [];

    protected string $brandId;

    public function __construct(string $brandId = 'test-brand-id')
    {
        $this->brandId = $brandId;
    }

    public function getBrandId(): string
    {
        return $this->brandId;
    }

    public function createClient(array $data): array
    {
        $id = 'cli_' . Str::random(20);

        $client = array_merge([
            'id' => $id,
            'email' => $data['email'] ?? 'test@example.com',
            'phone' => $data['phone'] ?? null,
            'full_name' => $data['full_name'] ?? 'Test User',
            'personal_code' => $data['personal_code'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'country' => $data['country'] ?? 'MY',
            'city' => $data['city'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'shipping_street_address' => $data['shipping_street_address'] ?? null,
            'shipping_country' => $data['shipping_country'] ?? null,
            'shipping_city' => $data['shipping_city'] ?? null,
            'shipping_zip_code' => $data['shipping_zip_code'] ?? null,
            'legal_name' => $data['legal_name'] ?? null,
            'brand_id' => $this->brandId,
            'bank_account' => $data['bank_account'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'tax_number' => $data['tax_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'created_on' => CarbonImmutable::now()->getTimestamp(),
            'updated_on' => CarbonImmutable::now()->getTimestamp(),
        ], $data);

        $this->clients[$id] = $client;

        return $client;
    }

    public function getClient(string $clientId): ?array
    {
        return $this->clients[$clientId] ?? null;
    }

    public function listClients(array $filters = []): array
    {
        $clients = array_values($this->clients);

        if (isset($filters['email'])) {
            $clients = array_filter($clients, fn ($c) => $c['email'] === $filters['email']);
        }

        return [
            'results' => array_values($clients),
            'count' => count($clients),
        ];
    }

    public function updateClient(string $clientId, array $data): ?array
    {
        if (! isset($this->clients[$clientId])) {
            return null;
        }

        $this->clients[$clientId] = array_merge($this->clients[$clientId], $data);
        $this->clients[$clientId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->clients[$clientId];
    }

    public function deleteClient(string $clientId): void
    {
        unset($this->clients[$clientId]);
    }

    public function createPurchase(array $data, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= $data['idempotency_key'] ?? null;

        if ($idempotencyKey !== null && ! is_string($idempotencyKey)) {
            throw new ChipValidationException('Idempotency key must be a string.');
        }

        if ($idempotencyKey !== null && isset($this->purchasesByKey[$idempotencyKey])) {
            $fingerprint = $this->fingerprintPayload($data);

            if (! hash_equals($this->purchaseFingerprintsByKey[$idempotencyKey], $fingerprint)) {
                throw new ChipValidationException(
                    'Idempotency key has already been used for a different purchase payload.'
                );
            }

            return $this->purchasesByKey[$idempotencyKey];
        }

        $id = 'pur_' . Str::random(20);

        $purchase = array_merge([
            'id' => $id,
            'client_id' => $data['client_id'] ?? null,
            'brand_id' => $this->brandId,
            'status' => 'created',
            'payment_method_whitelist' => $data['payment_method_whitelist'] ?? null,
            'is_recurring_token' => $data['is_recurring_token'] ?? false,
            'skip_capture' => $data['skip_capture'] ?? false,
            'client' => $data['client'] ?? null,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/' . $id,
            'direct_post_url' => 'https://gate.chip-in.asia/direct-post/' . $id,
            'success_redirect' => $data['success_redirect'] ?? null,
            'failure_redirect' => $data['failure_redirect'] ?? null,
            'cancel_redirect' => $data['cancel_redirect'] ?? null,
            'success_callback' => $data['success_callback'] ?? null,
            'creator_agent' => $data['creator_agent'] ?? 'FakeChipClient',
            'reference' => $data['reference'] ?? null,
            'issued' => CarbonImmutable::now()->toIso8601String(),
            'due' => $data['due'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'platform' => $data['platform'] ?? 'api',
            'send_receipt' => $data['send_receipt'] ?? false,
            'created_on' => CarbonImmutable::now()->getTimestamp(),
            'updated_on' => CarbonImmutable::now()->getTimestamp(),
        ], $data);

        $products = [];
        if (isset($purchase['purchase']['products']) && is_array($purchase['purchase']['products'])) {
            $products = $purchase['purchase']['products'];
        }

        $purchase['purchase'] = array_merge([
            'products' => $products,
            'currency' => $purchase['purchase']['currency'] ?? 'MYR',
            'total' => $purchase['purchase']['total'] ?? $this->calculateTotal($products),
            'total_override' => $purchase['purchase']['total_override'] ?? null,
            'language' => $purchase['purchase']['language'] ?? 'en',
            'notes' => $purchase['purchase']['notes'] ?? null,
        ], is_array($purchase['purchase'] ?? null) ? $purchase['purchase'] : []);

        if ($idempotencyKey !== null) {
            $purchase['idempotency_key'] = $idempotencyKey;
            $this->purchasesByKey[$idempotencyKey] = $purchase;
            $this->purchaseFingerprintsByKey[$idempotencyKey] = $this->fingerprintPayload($data);
        }

        $this->purchases[$id] = $purchase;

        return $purchase;
    }

    /**
     * Payload identity for the keyed store. Mirrors production semantics:
     * keys are sorted recursively and the staged idempotency key is not part
     * of the fingerprinted payload, so reordered but equivalent payloads —
     * and the same key supplied either staged or as the second argument —
     * collapse instead of conflicting.
     *
     * @param  array<string, mixed>  $data
     */
    private function fingerprintPayload(array $data): string
    {
        unset($data['idempotency_key']);

        return sha1((string) json_encode($this->sortForFingerprint($data)));
    }

    private function sortForFingerprint(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortForFingerprint($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortForFingerprint($item);
        }

        return $value;
    }

    public function getPurchase(string $purchaseId): ?array
    {
        return $this->purchases[$purchaseId] ?? null;
    }

    public function cancelPurchase(string $purchaseId): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'cancelled';
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Arm the next refundPurchase() call to return a pending_refund
     * purchase instead of a completed refund payment. One-shot: the flag is
     * consumed by the next refund of a known purchase.
     */
    public function pendingRefundOnce(): void
    {
        $this->pendingRefundOnce = true;
    }

    public function refundPurchase(string $purchaseId, ?int $amount = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $purchase = $this->purchases[$purchaseId];
        $refundAmount = $amount ?? $purchase['purchase']['total'];
        $now = CarbonImmutable::now()->getTimestamp();

        if ($this->pendingRefundOnce) {
            $this->pendingRefundOnce = false;
            $this->purchases[$purchaseId]['status'] = 'pending_refund';
            $this->purchases[$purchaseId]['updated_on'] = $now;

            return $this->purchases[$purchaseId];
        }

        $this->purchases[$purchaseId]['status'] = 'refunded';
        $this->purchases[$purchaseId]['refunded_amount'] = $refundAmount;
        $this->purchases[$purchaseId]['refundable_amount'] = max(
            0,
            (int) ($purchase['purchase']['total'] ?? 0) - $refundAmount,
        );
        $this->purchases[$purchaseId]['updated_on'] = $now;

        return [
            'id' => 'pay_' . Str::random(20),
            'type' => 'payment',
            'created_on' => $now,
            'updated_on' => $now,
            'client' => is_array($purchase['client'] ?? null) ? $purchase['client'] : null,
            'payment' => [
                'is_outgoing' => true,
                'payment_type' => 'refund',
                'amount' => $refundAmount,
                'currency' => $purchase['purchase']['currency'] ?? 'MYR',
                'net_amount' => $refundAmount,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'pending_unfreeze_on' => null,
                'description' => 'Refund',
                'paid_on' => $now,
                'remote_paid_on' => null,
            ],
            'transaction_data' => [
                'payment_method' => '',
                'extra' => [],
                'country' => '',
                'attempts' => [],
            ],
            'related_to' => [
                'type' => 'purchase',
                'id' => $purchaseId,
            ],
            'reference_generated' => $purchase['reference_generated'] ?? null,
            'reference' => $purchase['reference'] ?? null,
            'account_id' => null,
            'company_id' => $purchase['company_id'] ?? null,
            'is_test' => true,
            'user_id' => $purchase['user_id'] ?? null,
            'brand_id' => $purchase['brand_id'] ?? $this->brandId,
        ];
    }

    public function chargePurchase(string $purchaseId, string $recurringToken): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['recurring_token'] = $recurringToken;
        $this->purchases[$purchaseId]['payment'] = [
            'is_outgoing' => false,
            'payment_type' => 'purchase',
            'amount' => $this->purchases[$purchaseId]['purchase']['total'],
            'currency' => $this->purchases[$purchaseId]['purchase']['currency'],
            'net_amount' => $this->purchases[$purchaseId]['purchase']['total'],
            'fee_amount' => 0,
            'pending_amount' => 0,
            'description' => null,
            'paid_on' => CarbonImmutable::now()->getTimestamp(),
            'remote_paid_on' => null,
            'pending_unfreeze_on' => null,
        ];
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function capturePurchase(string $purchaseId, ?int $amount = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['captured_amount'] = $amount ?? $this->purchases[$purchaseId]['purchase']['total'];
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function releasePurchase(string $purchaseId): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'released';
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['payment'] = [
            'is_outgoing' => false,
            'payment_type' => 'purchase',
            'amount' => $this->purchases[$purchaseId]['purchase']['total'],
            'currency' => $this->purchases[$purchaseId]['purchase']['currency'],
            'net_amount' => $this->purchases[$purchaseId]['purchase']['total'],
            'fee_amount' => 0,
            'pending_amount' => 0,
            'description' => null,
            'paid_on' => $paidOn ?? CarbonImmutable::now()->getTimestamp(),
            'remote_paid_on' => null,
            'pending_unfreeze_on' => null,
        ];
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function deleteRecurringToken(string $purchaseId): void
    {
        if (isset($this->purchases[$purchaseId])) {
            unset($this->purchases[$purchaseId]['recurring_token']);
        }
    }

    public function getPaymentMethods(array $filters = []): array
    {
        return [
            [
                'name' => 'fpx',
                'logo' => 'https://example.com/fpx.png',
                'available_banks' => [
                    ['name' => 'Maybank', 'code' => 'MBB0228'],
                    ['name' => 'CIMB Bank', 'code' => 'BCBB0235'],
                    ['name' => 'Public Bank', 'code' => 'PBB0233'],
                ],
            ],
            [
                'name' => 'card',
                'logo' => 'https://example.com/card.png',
            ],
            [
                'name' => 'ewallet',
                'logo' => 'https://example.com/ewallet.png',
            ],
        ];
    }

    public function getPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0...(fake)\n-----END PUBLIC KEY-----";
    }

    public function getAccountBalance(array $filters = []): array
    {
        return [
            'balance' => 1000000,
            'currency' => 'MYR',
            'available' => 900000,
            'pending' => 100000,
        ];
    }

    public function getAccountTurnover(array $filters = []): array
    {
        return [
            'total' => 5000000,
            'count' => 150,
            'currency' => 'MYR',
        ];
    }

    public function addRecurringToken(string $clientId, ?array $data = null): array
    {
        $tokenId = 'tok_' . Str::random(20);

        $token = array_merge([
            'id' => $tokenId,
            'type' => 'client_recurring_token',
            'payment_method' => $data['payment_method'] ?? 'visa',
            'description' => $data['description'] ?? '**** **** **** 4242',
            'created_on' => CarbonImmutable::now()->getTimestamp(),
            'updated_on' => CarbonImmutable::now()->getTimestamp(),
        ], $data ?? []);

        $effectiveTokenId = isset($token['id']) && is_string($token['id']) && $token['id'] !== ''
            ? $token['id']
            : $tokenId;

        $token['id'] = $effectiveTokenId;

        if (! isset($this->recurringTokens[$clientId])) {
            $this->recurringTokens[$clientId] = [];
        }

        $this->recurringTokens[$clientId][$effectiveTokenId] = $token;

        return $token;
    }

    public function listClientRecurringTokens(string $clientId): array
    {
        return array_values($this->recurringTokens[$clientId] ?? []);
    }

    public function getClientRecurringToken(string $clientId, string $tokenId): ?array
    {
        return $this->recurringTokens[$clientId][$tokenId] ?? null;
    }

    public function deleteClientRecurringToken(string $clientId, string $tokenId): void
    {
        unset($this->recurringTokens[$clientId][$tokenId]);
    }

    public function createWebhook(array $data): array
    {
        $id = 'whk_' . Str::random(20);

        $webhook = [
            'id' => $id,
            'url' => $data['url'] ?? '',
            'events' => $data['events'] ?? ['*'],
            'active' => $data['active'] ?? true,
            'brand_id' => $this->brandId,
            'created_on' => CarbonImmutable::now()->getTimestamp(),
        ];

        $this->webhooks[$id] = $webhook;

        return $webhook;
    }

    public function getWebhook(string $webhookId): ?array
    {
        return $this->webhooks[$webhookId] ?? null;
    }

    public function updateWebhook(string $webhookId, array $data): ?array
    {
        if (! isset($this->webhooks[$webhookId])) {
            return null;
        }

        $this->webhooks[$webhookId] = array_merge($this->webhooks[$webhookId], $data);

        return $this->webhooks[$webhookId];
    }

    public function deleteWebhook(string $webhookId): void
    {
        unset($this->webhooks[$webhookId]);
    }

    public function listWebhooks(array $filters = []): array
    {
        return [
            'results' => array_values($this->webhooks),
            'count' => count($this->webhooks),
        ];
    }

    public function simulatePaymentComplete(string $purchaseId, ?string $recurringToken = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $token = $recurringToken ?? 'tok_' . Str::random(20);

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['recurring_token'] = $token;
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'card',
            'psp' => 'test-psp',
            'paid_on' => CarbonImmutable::now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function simulatePaymentFailure(string $purchaseId, string $reason = 'Payment declined'): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'failed';
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'card',
            'error' => $reason,
            'failed_on' => CarbonImmutable::now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = CarbonImmutable::now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function getClients(): array
    {
        return $this->clients;
    }

    public function getPurchases(): array
    {
        return $this->purchases;
    }

    public function getRecurringTokens(): array
    {
        return $this->recurringTokens;
    }

    public function reset(): void
    {
        $this->clients = [];
        $this->purchases = [];
        $this->purchasesByKey = [];
        $this->purchaseFingerprintsByKey = [];
        $this->pendingRefundOnce = false;
        $this->recurringTokens = [];
        $this->webhooks = [];
    }

    protected function calculateTotal(array $products): int
    {
        $total = 0;

        foreach ($products as $product) {
            [$price, $quantity, $discount, $taxPercent, $totalPriceOverride] = self::validatedLine($product);

            // Over-gross discounts throw from the primitive, mirroring
            // the server's 400 product_subtotal_negative (P25g).
            $total += ProductData::lineTotalMinorUnits($price, $quantity, $discount, $taxPercent, $totalPriceOverride);
        }

        return $total;
    }

    /**
     * Mirror the server's product validation for fake totals: integer
     * minor amounts, a numeric zero-or-greater quantity (P24), and a
     * tax percent within the shared rule — instead of silently
     * casting malformed input to zeros.
     *
     * @return array{int, string, int, float|int|string, ?int}
     */
    private static function validatedLine(mixed $product): array
    {
        if (! is_array($product)) {
            throw new ChipValidationException('Fake purchase products must be arrays.');
        }

        $price = $product['price'] ?? 0;
        $quantity = $product['quantity'] ?? 1;
        $discount = $product['discount'] ?? 0;
        $taxPercent = $product['tax_percent'] ?? 0.0;
        $totalPriceOverride = $product['total_price_override'] ?? null;

        foreach (['price' => $price, 'discount' => $discount, 'total_price_override' => $totalPriceOverride] as $field => $amount) {
            if ($amount !== null && ! self::isMinorAmount($amount)) {
                throw new ChipValidationException("Fake purchase product {$field} must be an integer minor amount.");
            }
        }

        if (! is_numeric($quantity) || $quantity < 0) {
            throw new ChipValidationException('Fake purchase product quantity must be zero or greater.');
        }

        if ($price < 0 || $discount < 0 || ($totalPriceOverride !== null && $totalPriceOverride < 0)) {
            throw new ChipValidationException('Fake purchase product amounts must be non-negative.');
        }

        return [
            (int) $price,
            (string) $quantity,
            (int) $discount,
            TaxPercent::normalize($taxPercent),
            $totalPriceOverride !== null ? (int) $totalPriceOverride : null,
        ];
    }

    private static function isMinorAmount(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value) && floor($value) === $value) {
            return true;
        }

        return is_string($value) && preg_match('/^[+-]?\d+$/', $value) === 1;
    }
}
