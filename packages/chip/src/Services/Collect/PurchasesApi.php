<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class PurchasesApi extends CollectApi
{
    protected ?CacheRepository $cache;

    public function __construct(
        ?CacheRepository $cache,
        ChipCollectClient $client,
    ) {
        $this->cache = $cache;
        parent::__construct($client);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): PurchaseData
    {
        $idempotencyKey = $this->resolveIdempotencyKey($data, $idempotencyKey);
        $data['brand_id'] = $data['brand_id'] ?? $this->client->getBrandId();

        $this->validatePurchaseData($data);

        if ($idempotencyKey === null || $this->cache === null) {
            return $this->postCreate($data);
        }

        return $this->createIdempotently($data, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postCreate(array $data): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->post('purchases/', $data),
            'Failed to create CHIP purchase',
            ['data' => $data]
        );

        return PurchaseData::from($response);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createIdempotently(array $data, string $idempotencyKey): PurchaseData
    {
        /** @var CacheRepository $cache */
        $cache = $this->cache;
        $cacheKey = $this->idempotencyCacheKey((string) $data['brand_id'], $idempotencyKey);
        $fingerprint = $this->payloadFingerprint($data);

        $cachedPurchase = $this->resolveCachedPurchase($cache->get($cacheKey), $fingerprint);
        if ($cachedPurchase !== null) {
            return $cachedPurchase;
        }

        $store = $cache->getStore();
        if (! $store instanceof LockProvider) {
            throw new ChipValidationException(
                'Purchase idempotency requires a lock-capable cache store.'
            );
        }

        $lock = $store->lock(
            $cacheKey . ':lock',
            max(1, (int) config('chip.http.timeout', 30) + 10)
        );

        return $lock->block(
            max(1, (int) config('chip.http.timeout', 30)),
            function () use ($cache, $cacheKey, $data, $fingerprint): PurchaseData {
                $cachedPurchase = $this->resolveCachedPurchase($cache->get($cacheKey), $fingerprint);
                if ($cachedPurchase !== null) {
                    return $cachedPurchase;
                }

                $purchase = $this->postCreate($data);

                $cache->put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'purchase' => $purchase->toArray(),
                ], max(1, (int) (config('chip.cache.ttl.purchase_idempotency') ?? config('chip.cache.default_ttl', 3600))));

                return $purchase;
            }
        );
    }

    private function resolveCachedPurchase(mixed $cached, string $fingerprint): ?PurchaseData
    {
        if ($cached === null) {
            return null;
        }

        if (! is_array($cached)
            || ! is_string($cached['fingerprint'] ?? null)
            || ! is_array($cached['purchase'] ?? null)) {
            throw new ChipValidationException('Cached idempotent purchase response is invalid.');
        }

        if (! hash_equals($fingerprint, $cached['fingerprint'])) {
            throw new ChipValidationException(
                'Idempotency key has already been used for a different purchase payload.'
            );
        }

        /** @var array<string, mixed> $purchase */
        $purchase = $cached['purchase'];

        return PurchaseData::from($purchase);
    }

    private function idempotencyCacheKey(string $brandId, string $idempotencyKey): string
    {
        $owner = OwnerContext::resolve();
        if ((bool) config('chip.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Purchase idempotency requires an owner context or explicit global context.'
            );
        }

        $ownerKey = (bool) config('chip.owner.enabled', false)
            ? OwnerScopeKey::forOwner($owner)
            : OwnerScopeKey::GLOBAL;

        return (string) config('chip.cache.prefix', 'chip:')
            . 'purchase_idempotency:'
            . $ownerKey
            . ':'
            . hash('sha256', $brandId . '|' . $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payloadFingerprint(array $data): string
    {
        return hash('sha256', json_encode($this->sortForFingerprint($data), JSON_THROW_ON_ERROR));
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveIdempotencyKey(array &$data, ?string $idempotencyKey): ?string
    {
        if (array_key_exists('idempotency_key', $data)) {
            $payloadKey = $data['idempotency_key'];
            unset($data['idempotency_key']);

            if ($idempotencyKey === null) {
                if ($payloadKey !== null && ! is_string($payloadKey)) {
                    throw new ChipValidationException('Idempotency key must be a string.');
                }

                $idempotencyKey = $payloadKey;
            }
        }

        if ($idempotencyKey === null) {
            $idempotencyKey = $data['reference'] ?? null;
        }

        if ($idempotencyKey === null) {
            return null;
        }

        $idempotencyKey = mb_trim($idempotencyKey);

        return $idempotencyKey === '' ? null : $idempotencyKey;
    }

    public function find(string $purchaseId): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->get("purchases/{$purchaseId}/"),
            'Failed to retrieve CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return PurchaseData::from($response);
    }

    public function cancel(string $purchaseId): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/cancel/"),
            'Failed to cancel CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return PurchaseData::from($response);
    }

    public function refund(string $purchaseId, ?int $amount = null): PurchaseData | PaymentData
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/refund/", $payload),
            'Failed to refund CHIP purchase',
            ['purchase_id' => $purchaseId, 'amount' => $amount]
        );

        if (($response['status'] ?? null) === 'pending_refund') {
            return PurchaseData::from($response);
        }

        return match ($response['type'] ?? null) {
            'payment' => PaymentData::from($response),
            'purchase' => PurchaseData::from($response),
            default => throw new ChipValidationException('CHIP refund response has an unsupported resource type or status.'),
        };
    }

    public function charge(string $purchaseId, string $recurringToken): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/charge/", [
                'recurring_token' => $recurringToken,
            ]),
            'Failed to charge CHIP purchase',
            ['purchase_id' => $purchaseId, 'recurring_token' => $recurringToken]
        );

        return PurchaseData::from($response);
    }

    public function capture(string $purchaseId, ?int $amount = null): PurchaseData
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/capture/", $payload),
            'Failed to capture CHIP purchase',
            ['purchase_id' => $purchaseId, 'amount' => $amount]
        );

        return PurchaseData::from($response);
    }

    public function release(string $purchaseId): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/release/"),
            'Failed to release CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return PurchaseData::from($response);
    }

    public function markAsPaid(string $purchaseId, ?int $paidOn = null): PurchaseData
    {
        $payload = [];
        if ($paidOn !== null) {
            $payload['paid_on'] = $paidOn;
        }

        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/mark_as_paid/", $payload),
            'Failed to mark CHIP purchase as paid',
            ['purchase_id' => $purchaseId, 'paid_on' => $paidOn]
        );

        return PurchaseData::from($response);
    }

    public function resendInvoice(string $purchaseId): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/resend_invoice/"),
            'Failed to resend CHIP purchase invoice',
            ['purchase_id' => $purchaseId]
        );

        return PurchaseData::from($response);
    }

    public function deleteRecurringToken(string $purchaseId): PurchaseData
    {
        $response = $this->attempt(
            fn () => $this->client->post("purchases/{$purchaseId}/delete_recurring_token/"),
            'Failed to delete CHIP recurring token',
            ['purchase_id' => $purchaseId]
        );

        return PurchaseData::from($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paymentMethods(array $filters = []): array
    {
        if ($this->cache === null) {
            return $this->fetchPaymentMethods($filters);
        }

        $cacheKey = config('chip.cache.prefix', 'chip:') . 'payment_methods:' . md5((string) json_encode($filters));
        $ttl = config('chip.cache.ttl.payment_methods')
            ?? config('chip.cache.default_ttl', 3600);

        return $this->cache->remember($cacheKey, $ttl, fn () => $this->fetchPaymentMethods($filters));
    }

    /**
     * @param  array<int, ProductData>  $products
     * @param  array<string, mixed>  $options
     */
    public function createCheckoutPurchase(array $products, ClientDetailsData $client, array $options = []): PurchaseData
    {
        $data = [
            'client' => $client->toArray(),
            'purchase' => [
                'products' => array_map(
                    fn (ProductData $product) => $product->toArray(),
                    $products
                ),
                'currency' => $options['currency'] ?? config('chip.defaults.currency', 'MYR'),
            ],
            'brand_id' => $this->client->getBrandId(),
            'send_receipt' => $options['send_receipt'] ?? config('chip.defaults.send_receipt', false),
            'creator_agent' => config('chip.defaults.creator_agent', 'Laravel Package'),
            'platform' => config('chip.defaults.platform', 'api'),
        ];

        if (! empty($options['purchase_overrides']) && is_array($options['purchase_overrides'])) {
            $data['purchase'] = array_merge(
                $data['purchase'],
                array_filter(
                    $options['purchase_overrides'],
                    static fn ($value) => $value !== null
                )
            );
        }

        // Only add reference if it's not null/empty
        if (! empty($options['reference'])) {
            $data['reference'] = $options['reference'];
        }

        if (! empty($options['payment_method_whitelist'])) {
            $data['payment_method_whitelist'] = is_string($options['payment_method_whitelist'])
                ? array_filter(array_map('trim', explode(',', $options['payment_method_whitelist'])))
                : $options['payment_method_whitelist'];
        } else {
            $defaultWhitelist = array_filter(array_map('trim', explode(',', (string) config('chip.defaults.payment_method_whitelist', ''))));

            if ($defaultWhitelist !== []) {
                $data['payment_method_whitelist'] = $defaultWhitelist;
            }
        }

        foreach ([
            'success_redirect' => $options['success_redirect'] ?? config('chip.defaults.success_redirect'),
            'failure_redirect' => $options['failure_redirect'] ?? config('chip.defaults.failure_redirect'),
            'cancel_redirect' => $options['cancel_redirect'] ?? null,
            'success_callback' => $options['success_callback'] ?? null,
        ] as $key => $value) {
            if (! empty($value)) {
                $data[$key] = $value;
            }
        }

        return $this->create($data);
    }

    public function publicKey(): string
    {
        $key = $this->attempt(
            fn () => $this->client->get('public_key/'),
            'Failed to get CHIP public key'
        );

        return is_string($key) ? $key : '';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function fetchPaymentMethods(array $filters): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'payment_methods/' . ($queryString ? '?' . $queryString : '');

        return $this->attempt(
            fn () => $this->client->get($endpoint),
            'Failed to get CHIP payment methods',
            ['filters' => $filters]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validatePurchaseData(array $data): void
    {
        if (! isset($data['purchase']) || ! is_array($data['purchase'])) {
            throw new ChipValidationException('Purchase payload is required');
        }

        if (empty($data['brand_id'])) {
            throw new ChipValidationException('brand_id is required');
        }

        $hasClientPayload = isset($data['client']) && is_array($data['client']);
        $hasClientId = isset($data['client_id']) && ! empty($data['client_id']);

        if (! $hasClientPayload && ! $hasClientId) {
            throw new ChipValidationException('Either client or client_id must be provided');
        }

        if ($hasClientPayload && empty($data['client']['email'])) {
            throw new ChipValidationException('client.email is required when client payload is provided');
        }

        if (! isset($data['purchase']['products']) || empty($data['purchase']['products'])) {
            throw new ChipValidationException('Purchase must have at least one product');
        }

        foreach ($data['purchase']['products'] as $product) {
            if (! isset($product['name']) || ! isset($product['price'])) {
                throw new ChipValidationException('Each product must have name and price');
            }
        }
    }
}
