<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Testing;

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
            'created_on' => now()->getTimestamp(),
            'updated_on' => now()->getTimestamp(),
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
        $this->clients[$clientId]['updated_on'] = now()->getTimestamp();

        return $this->clients[$clientId];
    }

    public function deleteClient(string $clientId): void
    {
        unset($this->clients[$clientId]);
    }

    public function createPurchase(array $data): array
    {
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
            'issued' => now()->toIso8601String(),
            'due' => $data['due'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'platform' => $data['platform'] ?? 'api',
            'send_receipt' => $data['send_receipt'] ?? false,
            'created_on' => now()->getTimestamp(),
            'updated_on' => now()->getTimestamp(),
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

        $this->purchases[$id] = $purchase;

        return $purchase;
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
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function refundPurchase(string $purchaseId, ?int $amount = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'refunded';
        $this->purchases[$purchaseId]['refunded_amount'] = $amount ?? $this->purchases[$purchaseId]['purchase']['total'];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function chargePurchase(string $purchaseId, string $recurringToken): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['recurring_token'] = $recurringToken;
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'card',
            'psp' => 'test-psp',
            'paid_on' => now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function capturePurchase(string $purchaseId, ?int $amount = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['captured_amount'] = $amount ?? $this->purchases[$purchaseId]['purchase']['total'];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function releasePurchase(string $purchaseId): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'released';
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'manual',
            'paid_on' => $paidOn ?? now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

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

    public function getAccountBalance(): array
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
            'recurring_token' => $tokenId,
            'type' => $data['type'] ?? 'card',
            'card_brand' => $data['card_brand'] ?? 'Visa',
            'brand' => $data['card_brand'] ?? 'Visa',
            'last_4' => $data['last_4'] ?? '4242',
            'card_last_4' => $data['last_4'] ?? '4242',
            'exp_month' => $data['exp_month'] ?? 12,
            'exp_year' => $data['exp_year'] ?? 2030,
            'client_id' => $clientId,
            'created_on' => now()->getTimestamp(),
        ], $data ?? []);

        $effectiveTokenId = isset($token['id']) && is_string($token['id']) && $token['id'] !== ''
            ? $token['id']
            : $tokenId;

        $token['id'] = $effectiveTokenId;

        if (! isset($token['recurring_token']) || ! is_string($token['recurring_token']) || $token['recurring_token'] === '') {
            $token['recurring_token'] = $effectiveTokenId;
        }

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
            'created_on' => now()->getTimestamp(),
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
            'paid_on' => now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

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
            'failed_on' => now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

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
        $this->recurringTokens = [];
        $this->webhooks = [];
    }

    protected function calculateTotal(array $products): int
    {
        $total = 0;

        foreach ($products as $product) {
            $price = (int) ($product['price'] ?? 0);
            $qty = (int) ($product['quantity'] ?? 1);
            $discount = (int) ($product['discount'] ?? 0);

            $lineTotal = $price * $qty;
            $total += max(0, $lineTotal - $discount);
        }

        return $total;
    }
}
