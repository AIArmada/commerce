<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Support\PurchaseIdempotencyLedger;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class PurchasesApi extends CollectApi
{
    protected ?CacheRepository $cache;

    private PurchaseIdempotencyLedger $purchaseIdempotencyLedger;

    public function __construct(
        ?CacheRepository $cache,
        ChipCollectClient $client,
    ) {
        $this->cache = $cache;
        $this->purchaseIdempotencyLedger = new PurchaseIdempotencyLedger;
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

        if ($idempotencyKey === null) {
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

        $purchase = PurchaseData::from($response);
        $requestCurrency = $this->normalizeCurrency($data['purchase']['currency'] ?? null);

        if ($purchase->getCurrency() !== $requestCurrency) {
            throw new ChipValidationException('CHIP returned a purchase in an unexpected currency.', [
                'request_currency' => $requestCurrency,
                'response_currency' => $purchase->getCurrency(),
            ]);
        }

        $expectedTotal = $data['purchase']['total_override'] ?? $data['purchase']['total'] ?? null;
        if ($expectedTotal !== null && $purchase->getAmountInCents() !== $this->normalizeMinorAmount($expectedTotal, 'purchase total')) {
            throw new ChipValidationException('CHIP returned a purchase with an unexpected total.', [
                'expected_total' => $expectedTotal,
                'response_total' => $purchase->getAmountInCents(),
            ]);
        }

        return $purchase;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createIdempotently(array $data, string $idempotencyKey): PurchaseData
    {
        $cacheKey = $this->idempotencyCacheKey((string) $data['brand_id'], $idempotencyKey);
        $fingerprint = $this->payloadFingerprint($data);

        $cachedPurchase = $this->resolveCachedPurchase($this->cache?->get($cacheKey), $fingerprint);
        if ($cachedPurchase !== null) {
            return $cachedPurchase;
        }

        $ledgerPurchase = $this->purchaseIdempotencyLedger->find(
            (string) $data['brand_id'],
            $idempotencyKey,
            $fingerprint,
        );
        if ($ledgerPurchase !== null) {
            return $ledgerPurchase;
        }

        if ($this->cache === null) {
            return $this->createAndRecord($data, $idempotencyKey, $fingerprint);
        }

        $cache = $this->cache;
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
            function () use ($cache, $cacheKey, $data, $fingerprint, $idempotencyKey): PurchaseData {
                $cachedPurchase = $this->resolveCachedPurchase($cache->get($cacheKey), $fingerprint);
                if ($cachedPurchase !== null) {
                    return $cachedPurchase;
                }

                $ledgerPurchase = $this->purchaseIdempotencyLedger->find(
                    (string) $data['brand_id'],
                    $idempotencyKey,
                    $fingerprint,
                );
                if ($ledgerPurchase !== null) {
                    return $ledgerPurchase;
                }

                $purchase = $this->createAndRecord(
                    $data,
                    $idempotencyKey,
                    $fingerprint,
                );

                $cache->put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'purchase' => $purchase->toArray(),
                ], max(1, (int) (config('chip.cache.ttl.purchase_idempotency') ?? config('chip.cache.default_ttl', 3600))));

                return $purchase;
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAndRecord(array $data, string $idempotencyKey, string $fingerprint): PurchaseData
    {
        $ledger = $this->purchaseIdempotencyLedger->reserve(
            (string) $data['brand_id'],
            $idempotencyKey,
            $fingerprint,
            $data,
        );
        $purchase = $this->postCreate($data);
        $this->purchaseIdempotencyLedger->record($ledger, $purchase);

        return $purchase;
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
        return (string) config('chip.cache.prefix', 'chip:')
            . 'purchase_idempotency:'
            . $this->ownerScopeKey()
            . ':'
            . hash('sha256', $brandId . '|' . $idempotencyKey);
    }

    private function mutationCacheKey(string $operation, string $purchaseId, array $payload): string
    {
        return (string) config('chip.cache.prefix', 'chip:')
            . 'purchase_mutation:'
            . $this->ownerScopeKey()
            . ':'
            . hash('sha256', json_encode([
                'operation' => $operation,
                'purchase_id' => $purchaseId,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));
    }

    private function ownerScopeKey(): string
    {
        $owner = OwnerContext::resolve();
        if ((bool) config('chip.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Purchase idempotency requires an owner context or explicit global context.'
            );
        }

        return (bool) config('chip.owner.enabled', false)
            ? OwnerScopeKey::forOwner($owner)
            : OwnerScopeKey::GLOBAL;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function postMutation(
        string $operation,
        string $purchaseId,
        string $endpoint,
        array $payload,
        string $message,
        array $context,
        bool $sendEmptyPayload = false,
    ): array {
        $fingerprint = $this->payloadFingerprint([
            'operation' => $operation,
            'purchase_id' => $purchaseId,
            'payload' => $payload,
        ]);
        $cacheKey = $this->mutationCacheKey($operation, $purchaseId, $payload);
        $cache = $this->cache;

        if ($cache === null) {
            return $this->performMutation($endpoint, $payload, $message, $context, $sendEmptyPayload);
        }

        $cachedResponse = $this->resolveCachedMutation($cache->get($cacheKey), $fingerprint);
        if ($cachedResponse !== null) {
            return $cachedResponse;
        }

        $store = $cache->getStore();
        if (! $store instanceof LockProvider) {
            throw new ChipValidationException(
                'CHIP purchase mutation idempotency requires a lock-capable cache store.'
            );
        }

        $lock = $store->lock(
            $cacheKey . ':lock',
            max(1, (int) config('chip.http.timeout', 30) + 10)
        );

        return $lock->block(
            max(1, (int) config('chip.http.timeout', 30)),
            function () use ($cache, $cacheKey, $fingerprint, $endpoint, $payload, $message, $context, $sendEmptyPayload): array {
                $cachedResponse = $this->resolveCachedMutation($cache->get($cacheKey), $fingerprint);
                if ($cachedResponse !== null) {
                    return $cachedResponse;
                }

                $response = $this->performMutation($endpoint, $payload, $message, $context, $sendEmptyPayload);

                $cache->put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'response' => $response,
                ], max(1, (int) (config('chip.cache.ttl.purchase_idempotency') ?? config('chip.cache.default_ttl', 3600))));

                return $response;
            }
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function performMutation(
        string $endpoint,
        array $payload,
        string $message,
        array $context,
        bool $sendEmptyPayload,
    ): array {
        return $this->attempt(
            fn () => $payload === [] && ! $sendEmptyPayload
                ? $this->client->post($endpoint)
                : $this->client->post($endpoint, $payload),
            $message,
            $context,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCachedMutation(mixed $cached, string $fingerprint): ?array
    {
        if ($cached === null) {
            return null;
        }

        if (! is_array($cached)
            || ! is_string($cached['fingerprint'] ?? null)
            || ! is_array($cached['response'] ?? null)) {
            throw new ChipValidationException('Cached CHIP purchase mutation response is invalid.');
        }

        if (! hash_equals($fingerprint, $cached['fingerprint'])) {
            throw new ChipValidationException(
                'CHIP purchase mutation key has already been used for a different payload.'
            );
        }

        /** @var array<string, mixed> $response */
        $response = $cached['response'];

        return $response;
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
        $explicit = $idempotencyKey !== null;

        if (array_key_exists('idempotency_key', $data)) {
            $payloadKey = $data['idempotency_key'];
            unset($data['idempotency_key']);

            if ($idempotencyKey === null) {
                if ($payloadKey !== null && ! is_string($payloadKey)) {
                    throw new ChipValidationException('Idempotency key must be a string.');
                }

                $explicit = $payloadKey !== null;
                $idempotencyKey = $payloadKey;
            }
        }

        if ($idempotencyKey === null && ! $explicit) {
            $idempotencyKey = $data['reference'] ?? null;
        }

        if ($idempotencyKey === null) {
            return null;
        }

        if (! is_string($idempotencyKey)) {
            throw new ChipValidationException('Idempotency key must be a string.');
        }

        $trimmed = mb_trim($idempotencyKey);

        if ($trimmed === '' && $explicit) {
            throw new ChipValidationException('Idempotency key must be a non-empty string.');
        }

        return $trimmed === '' ? null : $trimmed;
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
        $response = $this->postMutation(
            operation: 'cancel',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/cancel/",
            payload: [],
            message: 'Failed to cancel CHIP purchase',
            context: ['purchase_id' => $purchaseId],
        );

        return PurchaseData::from($response);
    }

    public function refund(string $purchaseId, ?int $amount = null): PurchaseData | PaymentData
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->postMutation(
            operation: 'refund',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/refund/",
            payload: $payload,
            message: 'Failed to refund CHIP purchase',
            context: ['purchase_id' => $purchaseId, 'amount' => $amount],
            sendEmptyPayload: true,
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
        $response = $this->postMutation(
            operation: 'charge',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/charge/",
            payload: [
                'recurring_token' => $recurringToken,
            ],
            message: 'Failed to charge CHIP purchase',
            context: ['purchase_id' => $purchaseId, 'recurring_token' => $recurringToken],
        );

        return PurchaseData::from($response);
    }

    public function capture(string $purchaseId, ?int $amount = null): PurchaseData
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->postMutation(
            operation: 'capture',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/capture/",
            payload: $payload,
            message: 'Failed to capture CHIP purchase',
            context: ['purchase_id' => $purchaseId, 'amount' => $amount],
            sendEmptyPayload: true,
        );

        return PurchaseData::from($response);
    }

    public function release(string $purchaseId): PurchaseData
    {
        $response = $this->postMutation(
            operation: 'release',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/release/",
            payload: [],
            message: 'Failed to release CHIP purchase',
            context: ['purchase_id' => $purchaseId],
        );

        return PurchaseData::from($response);
    }

    public function markAsPaid(string $purchaseId, ?int $paidOn = null): PurchaseData
    {
        $payload = [];
        if ($paidOn !== null) {
            $payload['paid_on'] = $paidOn;
        }

        $response = $this->postMutation(
            operation: 'mark_as_paid',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/mark_as_paid/",
            payload: $payload,
            message: 'Failed to mark CHIP purchase as paid',
            context: ['purchase_id' => $purchaseId, 'paid_on' => $paidOn],
            sendEmptyPayload: true,
        );

        return PurchaseData::from($response);
    }

    public function resendInvoice(string $purchaseId): PurchaseData
    {
        $response = $this->postMutation(
            operation: 'resend_invoice',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/resend_invoice/",
            payload: [],
            message: 'Failed to resend CHIP purchase invoice',
            context: ['purchase_id' => $purchaseId],
        );

        return PurchaseData::from($response);
    }

    public function deleteRecurringToken(string $purchaseId): PurchaseData
    {
        $response = $this->postMutation(
            operation: 'delete_recurring_token',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/delete_recurring_token/",
            payload: [],
            message: 'Failed to delete CHIP recurring token',
            context: ['purchase_id' => $purchaseId],
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
        $currency = $this->normalizeCurrency($options['currency'] ?? config('chip.defaults.currency', 'MYR'));

        foreach ($products as $product) {
            if ($product->getCurrency() !== $currency) {
                throw new ChipValidationException('Checkout product currency must match the purchase currency.', [
                    'purchase_currency' => $currency,
                    'product_currency' => $product->getCurrency(),
                ]);
            }
        }

        /** @var array<string, mixed> $purchaseOverrides */
        $purchaseOverrides = ! empty($options['purchase_overrides']) && is_array($options['purchase_overrides'])
            ? array_filter(
                $options['purchase_overrides'],
                static fn ($value) => $value !== null
            )
            : [];
        $overrideCurrency = $this->normalizeCurrency($purchaseOverrides['currency'] ?? $currency);

        if ($overrideCurrency !== $currency) {
            throw new ChipValidationException('Checkout purchase currency cannot differ from its products.', [
                'purchase_currency' => $overrideCurrency,
                'product_currency' => $currency,
            ]);
        }

        $data = [
            'client' => $client->toArray(),
            'purchase' => [
                'products' => array_map(
                    fn (ProductData $product) => $product->toArray(),
                    $products
                ),
                'currency' => $currency,
            ],
            'brand_id' => $this->client->getBrandId(),
            'send_receipt' => $options['send_receipt'] ?? config('chip.defaults.send_receipt', false),
            'creator_agent' => config('chip.defaults.creator_agent', 'Laravel Package'),
            'platform' => config('chip.defaults.platform', 'api'),
        ];

        if ($purchaseOverrides !== []) {
            $data['purchase'] = array_merge(
                $data['purchase'],
                $purchaseOverrides
            );
        }

        $data['purchase']['currency'] = $overrideCurrency;

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

        $hasExplicitIdempotencyKey = array_key_exists('idempotency_key', $options)
            || array_key_exists('reference', $options);
        $idempotencyKey = $options['idempotency_key'] ?? $options['reference'] ?? null;

        if ($hasExplicitIdempotencyKey) {
            if (! is_string($idempotencyKey) || mb_trim($idempotencyKey) === '') {
                throw new ChipValidationException(
                    'Checkout purchases require a non-empty idempotency_key or reference.'
                );
            }

            $idempotencyKey = mb_trim($idempotencyKey);
        } else {
            $idempotencyKey = 'checkout-' . $this->payloadFingerprint($data);
        }

        $data['idempotency_key'] = $idempotencyKey;

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

        if (! isset($data['purchase']['products']) || ! is_array($data['purchase']['products']) || empty($data['purchase']['products'])) {
            throw new ChipValidationException('Purchase must have at least one product');
        }

        $currency = $this->normalizeCurrency($data['purchase']['currency'] ?? null);
        $subtotal = 0;

        foreach ($data['purchase']['products'] as $product) {
            if (! is_array($product) || ! isset($product['name']) || ! isset($product['price'])) {
                throw new ChipValidationException('Each product must have name and price');
            }

            $price = $this->normalizeMinorAmount($product['price'], 'product price');
            $quantity = $this->normalizeQuantity($product['quantity'] ?? 1);
            $discount = $this->normalizeMinorAmount($product['discount'] ?? 0, 'product discount');

            if ($price < 0 || $discount < 0 || $discount > $price) {
                throw new ChipValidationException('Product price and discount must be non-negative, with discount no greater than price.');
            }

            $subtotal += $price * $quantity;
        }

        $subtotalOverride = $this->nullableMinorAmount($data['purchase']['subtotal_override'] ?? null, 'subtotal override');
        $discountOverride = $this->nullableMinorAmount($data['purchase']['total_discount_override'] ?? null, 'total discount override');
        $taxOverride = $this->nullableMinorAmount($data['purchase']['total_tax_override'] ?? null, 'total tax override');
        $totalOverride = $this->nullableMinorAmount($data['purchase']['total_override'] ?? null, 'total override');

        if ($subtotalOverride !== null && $subtotalOverride !== $subtotal) {
            throw new ChipValidationException('Purchase subtotal override does not match the sum of line items.', [
                'line_items_subtotal' => $subtotal,
                'subtotal_override' => $subtotalOverride,
            ]);
        }

        if ($totalOverride !== null) {
            if ($subtotalOverride === null || $discountOverride === null || $taxOverride === null) {
                throw new ChipValidationException('Total override requires subtotal, discount, and tax overrides.');
            }

            $calculatedTotal = $subtotalOverride - $discountOverride + $taxOverride;
            if ($calculatedTotal !== $totalOverride) {
                throw new ChipValidationException('Purchase total overrides do not reconcile.', [
                    'calculated_total' => $calculatedTotal,
                    'total_override' => $totalOverride,
                ]);
            }
        }

        if (isset($data['purchase']['total'])) {
            $total = $this->normalizeMinorAmount($data['purchase']['total'], 'purchase total');

            if ($totalOverride !== null && $total !== $totalOverride) {
                throw new ChipValidationException('Purchase total must match the total override.', [
                    'total' => $total,
                    'total_override' => $totalOverride,
                ]);
            }
        }
    }

    private function normalizeCurrency(mixed $currency): string
    {
        if (! is_string($currency)) {
            throw new ChipValidationException('Purchase currency must be a three-letter ISO 4217 code.');
        }

        $currency = mb_strtoupper(mb_trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new ChipValidationException('Purchase currency must be a three-letter ISO 4217 code.');
        }

        return $currency;
    }

    private function normalizeMinorAmount(mixed $amount, string $field): int
    {
        if (is_int($amount)) {
            return $amount;
        }

        if (is_float($amount) && is_finite($amount) && floor($amount) === $amount) {
            return (int) $amount;
        }

        if (is_string($amount) && filter_var(mb_trim($amount), FILTER_VALIDATE_INT) !== false) {
            return (int) mb_trim($amount);
        }

        throw new ChipValidationException("{$field} must be an integer amount in minor units.");
    }

    private function nullableMinorAmount(mixed $amount, string $field): ?int
    {
        return $amount === null ? null : $this->normalizeMinorAmount($amount, $field);
    }

    private function normalizeQuantity(mixed $quantity): int
    {
        if (is_int($quantity)) {
            $normalized = $quantity;
        } elseif (is_float($quantity) && is_finite($quantity) && floor($quantity) === $quantity) {
            $normalized = (int) $quantity;
        } elseif (is_string($quantity) && filter_var(mb_trim($quantity), FILTER_VALIDATE_INT) !== false) {
            $normalized = (int) mb_trim($quantity);
        } else {
            throw new ChipValidationException('Product quantity must be an integer.');
        }

        if ($normalized < 1) {
            throw new ChipValidationException('Product quantity must be an integer greater than zero.');
        }

        return $normalized;
    }
}
