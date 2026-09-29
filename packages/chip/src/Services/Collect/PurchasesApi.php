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
use AIArmada\Chip\Support\TaxPercent;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class PurchasesApi extends CollectApi
{
    /**
     * Mutation operations safe to replay from cache.
     *
     * Refunds and captures are intentionally absent: two genuine same-amount
     * refunds (or captures) must both reach CHIP, so they are never served
     * from the mutation cache. Charge stays cached: retry-after-crash
     * deduplication for recurring-token charges is pinned by test coverage.
     *
     * @var array<int, string>
     */
    private const array CACHED_MUTATION_OPERATIONS = [
        'cancel',
        'charge',
        'release',
        'mark_as_paid',
        'resend_invoice',
        'delete_recurring_token',
    ];

    private const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

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
            // AGENTS: null key = no duplicate protection at all (plain POST).
            // Keyless callers accept double-create risk; see the keyless-path
            // invariant in cashier's ChipCheckoutBuilder for why one path
            // stays keyless deliberately.
            return $this->postCreate($data);
        }

        return $this->createIdempotently($data, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postCreate(array $data, ?string $idempotencyKey = null): PurchaseData
    {
        $headers = $idempotencyKey !== null && $idempotencyKey !== ''
            ? [self::IDEMPOTENCY_HEADER => $idempotencyKey]
            : [];

        $response = $this->attempt(
            fn () => $headers === []
                ? $this->client->post('purchases/', $data)
                : $this->client->post('purchases/', $data, $headers),
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

        $expectedTotal = $data['purchase']['total_override'] ?? null;
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
        $legacyFingerprints = $this->legacyIdempotencyFingerprints($data);

        $cachedPurchase = $this->resolveCachedPurchase($this->cache?->get($cacheKey), $fingerprint, $legacyFingerprints);
        if ($cachedPurchase !== null) {
            return $cachedPurchase;
        }

        $ledgerPurchase = $this->purchaseIdempotencyLedger->find(
            (string) $data['brand_id'],
            $idempotencyKey,
            $fingerprint,
            $legacyFingerprints,
        );
        if ($ledgerPurchase !== null) {
            return $ledgerPurchase;
        }

        if ($this->cache === null) {
            // AGENTS: no cache = no lock. Concurrent same-key requests can
            // both miss the ledger and both POST (the ledger has no DB
            // unique constraint to backstop the race). Production MUST use a
            // shared lock-capable store (e.g. redis); per-instance
            // file/array caches do NOT protect across servers/workers.
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
            function () use ($cache, $cacheKey, $data, $fingerprint, $idempotencyKey, $legacyFingerprints): PurchaseData {
                $cachedPurchase = $this->resolveCachedPurchase($cache->get($cacheKey), $fingerprint, $legacyFingerprints);
                if ($cachedPurchase !== null) {
                    return $cachedPurchase;
                }

                $ledgerPurchase = $this->purchaseIdempotencyLedger->find(
                    (string) $data['brand_id'],
                    $idempotencyKey,
                    $fingerprint,
                    $legacyFingerprints,
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
        $purchase = $this->postCreate($data, $idempotencyKey);
        $this->purchaseIdempotencyLedger->record($ledger, $purchase);

        return $purchase;
    }

    /**
     * @param  list<string>  $legacyFingerprints
     */
    private function resolveCachedPurchase(mixed $cached, string $fingerprint, array $legacyFingerprints = []): ?PurchaseData
    {
        if ($cached === null) {
            return null;
        }

        if (! is_array($cached)
            || ! is_string($cached['fingerprint'] ?? null)
            || ! is_array($cached['purchase'] ?? null)) {
            throw new ChipValidationException('Cached idempotent purchase response is invalid.');
        }

        foreach ([$fingerprint, ...$legacyFingerprints] as $candidate) {
            if (hash_equals($candidate, $cached['fingerprint'])) {
                /** @var array<string, mixed> $purchase */
                $purchase = $cached['purchase'];

                return PurchaseData::from($purchase);
            }
        }

        throw new ChipValidationException(
            'Idempotency key has already been used for a different purchase payload.'
        );
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
        ?string $idempotencyKey = null,
    ): array {
        if (! in_array($operation, self::CACHED_MUTATION_OPERATIONS, true)) {
            return $this->performMutation($endpoint, $payload, $message, $context, $sendEmptyPayload, $idempotencyKey);
        }

        $fingerprint = $this->payloadFingerprint([
            'operation' => $operation,
            'purchase_id' => $purchaseId,
            'payload' => $payload,
        ]);
        $cacheKey = $this->mutationCacheKey($operation, $purchaseId, $payload);
        $cache = $this->cache;

        if ($cache === null) {
            return $this->performMutation($endpoint, $payload, $message, $context, $sendEmptyPayload, $idempotencyKey);
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
            function () use ($cache, $cacheKey, $fingerprint, $endpoint, $payload, $message, $context, $sendEmptyPayload, $idempotencyKey): array {
                $cachedResponse = $this->resolveCachedMutation($cache->get($cacheKey), $fingerprint);
                if ($cachedResponse !== null) {
                    return $cachedResponse;
                }

                $response = $this->performMutation($endpoint, $payload, $message, $context, $sendEmptyPayload, $idempotencyKey);

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
        ?string $idempotencyKey = null,
    ): array {
        if ($idempotencyKey !== null && mb_trim($idempotencyKey) === '') {
            throw new ChipValidationException('Idempotency key must be a non-empty string.');
        }

        $headers = $idempotencyKey !== null ? [self::IDEMPOTENCY_HEADER => $idempotencyKey] : [];

        return $this->attempt(
            fn () => $this->postMutationPayload($endpoint, $payload, $sendEmptyPayload, $headers),
            $message,
            $context,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function postMutationPayload(string $endpoint, array $payload, bool $sendEmptyPayload, array $headers): array
    {
        if ($payload === [] && ! $sendEmptyPayload) {
            return $headers === []
                ? $this->client->post($endpoint)
                : $this->client->post($endpoint, [], $headers);
        }

        return $headers === []
            ? $this->client->post($endpoint, $payload)
            : $this->client->post($endpoint, $payload, $headers);
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
        return hash('sha256', json_encode($this->sortForFingerprint($this->canonicalIdempotencyPayload($data)), JSON_THROW_ON_ERROR));
    }

    /**
     * Normalize the payload for idempotency matching.
     *
     * Strips the fields the request cleanup removed (`purchase.total`, null
     * product categories) so equivalent payloads share one fingerprint
     * regardless of which serialization path produced them. Pre-cleanup
     * shapes are covered by legacyIdempotencyFingerprints().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function canonicalIdempotencyPayload(array $data): array
    {
        if (! isset($data['purchase']) || ! is_array($data['purchase'])) {
            return $data;
        }

        unset($data['purchase']['total']);

        if (! isset($data['purchase']['products']) || ! is_array($data['purchase']['products'])) {
            return $data;
        }

        foreach ($data['purchase']['products'] as $index => $product) {
            if (is_array($product) && ($product['category'] ?? null) === null) {
                unset($data['purchase']['products'][$index]['category']);
            }
        }

        return $data;
    }

    /**
     * Fingerprints matching pre-cleanup entry shapes. Match-only: new entries
     * are always recorded under payloadFingerprint().
     *
     * Pre-cleanup payloads carried two now-removed fields in path-dependent
     * combinations: `purchase.total` (builder paths, always equal to
     * `total_override`) and null product categories (object serialization
     * paths; the cents/line-item paths omitted the key). A retry cannot tell
     * which path stored the entry, so every combination is tried. The forms
     * are disjoint by field presence, so a genuine same-version conflict can
     * never match a legacy form.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function legacyIdempotencyFingerprints(array $data): array
    {
        $base = $this->canonicalIdempotencyPayload($data);

        $withNulls = $base;
        if (isset($withNulls['purchase']['products']) && is_array($withNulls['purchase']['products'])) {
            foreach ($withNulls['purchase']['products'] as $index => $product) {
                if (is_array($product) && ! array_key_exists('category', $product)) {
                    $withNulls['purchase']['products'][$index]['category'] = null;
                }
            }
        }

        $forms = [$withNulls];

        $override = $data['purchase']['total_override'] ?? null;
        if ($override !== null && isset($base['purchase']) && is_array($base['purchase'])) {
            $withTotal = $base;
            $withTotal['purchase']['total'] = $override;

            $withBoth = $withNulls;
            $withBoth['purchase']['total'] = $override;

            $forms[] = $withTotal;
            $forms[] = $withBoth;
        }

        $fingerprints = array_map(
            fn (array $form): string => hash('sha256', json_encode($this->sortForFingerprint($form), JSON_THROW_ON_ERROR)),
            $forms
        );

        return array_values(array_unique($fingerprints));
    }

    /**
     * Keyless-checkout key derivation. PERMANENTLY FROZEN.
     *
     * Re-adds `category: null` to products lacking the key (the pre-cleanup
     * object-serialization shape) and never adds `purchase.total` (the
     * checkout path never carried it). Keyless entries recorded before the
     * wire cleanup were keyed under this exact projection, so deriving the
     * retry key any other way would miss them and double-post.
     *
     * Do NOT "simplify" this to payloadFingerprint(), and do NOT evolve it
     * alongside canonicalIdempotencyPayload(): the duplication is the point.
     * Pinned by literal-hash tests in PurchaseIdempotencyCompatTest.
     *
     * @param  array<string, mixed>  $data
     */
    private function frozenCheckoutKeyFingerprint(array $data): string
    {
        if (isset($data['purchase']['products']) && is_array($data['purchase']['products'])) {
            foreach ($data['purchase']['products'] as $index => $product) {
                if (is_array($product) && ! array_key_exists('category', $product)) {
                    $data['purchase']['products'][$index]['category'] = null;
                }
            }
        }

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
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

        $response = $this->attempt(
            fn () => $this->client->get("purchases/{$purchaseId}/"),
            'Failed to retrieve CHIP purchase',
            ['purchase_id' => $purchaseId]
        );

        return PurchaseData::from($response);
    }

    public function cancel(string $purchaseId, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

        $response = $this->postMutation(
            operation: 'cancel',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/cancel/",
            payload: [],
            message: 'Failed to cancel CHIP purchase',
            context: ['purchase_id' => $purchaseId],
            idempotencyKey: $idempotencyKey,
        );

        return PurchaseData::from($response);
    }

    public function refund(string $purchaseId, ?int $amount = null, ?string $idempotencyKey = null): PurchaseData | PaymentData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

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
            idempotencyKey: $idempotencyKey,
        );

        if (($response['status'] ?? null) === 'pending_refund') {
            return PurchaseData::from($response);
        }

        if (array_key_exists('purchase', $response)) {
            return PurchaseData::from($response);
        }

        return match ($response['type'] ?? null) {
            'payment' => PaymentData::from($response),
            'purchase' => PurchaseData::from($response),
            default => throw new ChipValidationException('CHIP refund response has an unsupported resource type or status.'),
        };
    }

    public function charge(string $purchaseId, string $recurringToken, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

        $response = $this->postMutation(
            operation: 'charge',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/charge/",
            payload: [
                'recurring_token' => $recurringToken,
            ],
            message: 'Failed to charge CHIP purchase',
            context: ['purchase_id' => $purchaseId, 'recurring_token' => $recurringToken],
            idempotencyKey: $idempotencyKey,
        );

        return PurchaseData::from($response);
    }

    public function capture(string $purchaseId, ?int $amount = null, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

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
            idempotencyKey: $idempotencyKey,
        );

        return PurchaseData::from($response);
    }

    public function release(string $purchaseId, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

        $response = $this->postMutation(
            operation: 'release',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/release/",
            payload: [],
            message: 'Failed to release CHIP purchase',
            context: ['purchase_id' => $purchaseId],
            idempotencyKey: $idempotencyKey,
        );

        return PurchaseData::from($response);
    }

    public function markAsPaid(string $purchaseId, ?int $paidOn = null, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

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
            idempotencyKey: $idempotencyKey,
        );

        return PurchaseData::from($response);
    }

    public function resendInvoice(string $purchaseId, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

        $response = $this->postMutation(
            operation: 'resend_invoice',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/resend_invoice/",
            payload: [],
            message: 'Failed to resend CHIP purchase invoice',
            context: ['purchase_id' => $purchaseId],
            idempotencyKey: $idempotencyKey,
        );

        return PurchaseData::from($response);
    }

    public function deleteRecurringToken(string $purchaseId, ?string $idempotencyKey = null): PurchaseData
    {
        $this->assertSafePathSegment($purchaseId, 'Purchase id');

        $response = $this->postMutation(
            operation: 'delete_recurring_token',
            purchaseId: $purchaseId,
            endpoint: "purchases/{$purchaseId}/delete_recurring_token/",
            payload: [],
            message: 'Failed to delete CHIP recurring token',
            context: ['purchase_id' => $purchaseId],
            idempotencyKey: $idempotencyKey,
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
                    fn (ProductData $product) => $product->toRequestArray(),
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
            // Fingerprint invariant: this fingerprint default IS the resolved key,
            // so the header carries it like any explicit key (local and server
            // collapse together). The keyless checkout-builder path is a
            // separate route that must never gain fingerprinting: without a
            // stable operation identity it would break buy-twice-identical.
            $idempotencyKey = 'checkout-' . $this->frozenCheckoutKeyFingerprint($data);
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

        if (array_key_exists('platform', $data)) {
            if ($data['platform'] === null) {
                throw new ChipValidationException('Platform cannot be null.');
            }

            if (! in_array($data['platform'], ['web', 'api', 'ios', 'android', 'macos', 'windows'], true)) {
                throw new ChipValidationException('Platform must be one of: web, api, ios, android, macos, windows.');
            }
        }

        if (array_key_exists('creator_agent', $data)) {
            if ($data['creator_agent'] === null) {
                throw new ChipValidationException('Creator agent cannot be null.');
            }

            if (! is_string($data['creator_agent']) || mb_strlen($data['creator_agent']) > 32) {
                throw new ChipValidationException('Creator agent must be a string of at most 32 characters.');
            }
        }

        $hasClientPayload = isset($data['client']) && is_array($data['client']);
        $hasClientId = isset($data['client_id']) && ! empty($data['client_id']);

        $clientPresent = array_key_exists('client', $data) && $data['client'] !== null;
        $clientIdPresent = array_key_exists('client_id', $data) && $data['client_id'] !== null;

        if ($clientPresent && $clientIdPresent) {
            throw new ChipValidationException('Only one of client or client_id may be provided');
        }

        if (! $hasClientPayload && ! $hasClientId) {
            throw new ChipValidationException('Either client or client_id must be provided');
        }

        if ($hasClientPayload && empty($data['client']['email'])) {
            throw new ChipValidationException('client.email is required when client payload is provided');
        }

        if ($hasClientPayload) {
            $this->assertCappedLength($data['client'], 'legal_name', 1000, 'Client legal name');
            $this->assertCappedLength($data['client'], 'brand_name', 128, 'Client brand name');
            $this->assertCappedLength($data['client'], 'registration_number', 32, 'Client registration number');
            $this->assertCappedLength($data['client'], 'tax_number', 32, 'Client tax number');
            $this->assertCappedLength($data['client'], 'bank_account', 64, 'Client bank account');
            $this->assertCappedLength($data['client'], 'bank_code', 32, 'Client bank code');
            $this->assertCappedLength($data['client'], 'street_address', 128, 'Client street address');
            $this->assertCappedLength($data['client'], 'city', 128, 'Client city');
            $this->assertCappedLength($data['client'], 'zip_code', 32, 'Client ZIP code');
            $this->assertCappedLength($data['client'], 'state', 128, 'Client state');
            $this->assertCappedLength($data['client'], 'shipping_street_address', 128, 'Client shipping street address');
            $this->assertCappedLength($data['client'], 'shipping_city', 128, 'Client shipping city');
            $this->assertCappedLength($data['client'], 'shipping_zip_code', 32, 'Client shipping ZIP code');
            $this->assertCappedLength($data['client'], 'shipping_state', 128, 'Client shipping state');

            foreach (['cc', 'bcc'] as $list) {
                if (! array_key_exists($list, $data['client'])) {
                    continue;
                }

                if (! is_array($data['client'][$list])) {
                    throw new ChipValidationException("Client {$list} must be an array of email addresses.");
                }

                foreach ($data['client'][$list] as $email) {
                    $this->assertMaxLength($email, 254, "Client {$list} entries");
                }
            }
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
            $totalPriceOverride = ($product['total_price_override'] ?? null) !== null
                ? $this->normalizeMinorAmount($product['total_price_override'], 'product total price override')
                : null;

            // Discount is per-line: the server bound is discount <= price x quantity
            // (400 product_subtotal_negative beyond it, sandbox-proven).
            // Exact comparison: float64 misplaces valid boundary lines
            // (200 x "1.005" with discount 201 must pass).
            if ($price < 0 || $discount < 0 || ProductData::discountExceedsLineGross($discount, $price, $quantity)) {
                throw new ChipValidationException('Product price and discount must be non-negative, with discount no greater than price times quantity.');
            }

            if ($totalPriceOverride !== null && $totalPriceOverride < 0) {
                throw new ChipValidationException('Product total price override must be non-negative.');
            }

            $this->assertMaxLength($product['name'], 256, 'Product name');

            if (array_key_exists('category', $product) && $product['category'] !== null) {
                $this->assertMaxLength($product['category'], 256, 'Product category');
            }

            if (array_key_exists('tax_percent', $product)) {
                TaxPercent::normalize($product['tax_percent']);
            }

            $subtotal += $totalPriceOverride ?? ProductData::multiplyMinorUnits($price, (string) $quantity);
        }

        $this->assertCappedLength($data['purchase'], 'notes', 10000, 'Purchase notes');
        $this->assertCappedLength($data['purchase'], 'language', 2, 'Purchase language');
        $this->assertCappedLength($data['purchase'], 'email_message', 512, 'Purchase email message');

        if (array_key_exists('total', $data['purchase'])) {
            throw new ChipValidationException('purchase.total is server-calculated; use total_override.');
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

    private function assertMaxLength(mixed $value, int $max, string $field): void
    {
        if (! is_string($value) || mb_strlen($value) > $max) {
            throw new ChipValidationException("{$field} must be a string of at most {$max} characters.");
        }
    }

    private function assertCappedLength(array $data, string $key, int $max, string $field): void
    {
        if (array_key_exists($key, $data) && $data[$key] !== null) {
            $this->assertMaxLength($data[$key], $max, $field);
        }
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

    private function normalizeQuantity(mixed $quantity): int | string
    {
        if (is_int($quantity)) {
            $normalized = $quantity;
        } elseif (is_float($quantity)) {
            if (! is_finite($quantity)) {
                throw new ChipValidationException('Product quantity must be numeric.');
            }

            // 2**53 is the float64-exactness boundary; larger whole floats
            // would silently narrow on (int) cast, so they stay strings
            // for the range check below.
            $normalized = floor($quantity) === $quantity && $quantity < 2 ** 53 ? (int) $quantity : (string) $quantity;
        } elseif (is_string($quantity)) {
            $value = mb_trim($quantity);

            if ($value === '' || ! is_numeric($value) || ! is_finite((float) $value)) {
                throw new ChipValidationException('Product quantity must be numeric.');
            }

            $normalized = $value;
        } else {
            throw new ChipValidationException('Product quantity must be numeric.');
        }

        $asFloat = (float) $normalized;

        if ($asFloat < 0) {
            throw new ChipValidationException('Product quantity must be zero or greater.');
        }

        if ($asFloat >= 2 ** 53) {
            throw new ChipValidationException('Product quantity is out of range.');
        }

        if ($this->decimalPlaces((string) $normalized) > 4) {
            throw new ChipValidationException('Product quantity must have at most 4 decimal places.');
        }

        return $normalized;
    }

    /**
     * Count decimal places in a numeric string, mirroring how a decimal
     * field parses it: fraction digits minus the exponent, floored at 0.
     * String-counted (not float-rounded) so precision-collapsed forms
     * like "1.0000000000000001" cannot slip through a float cast.
     */
    private function decimalPlaces(string $value): int
    {
        $value = mb_ltrim($value, '+-');
        $exponent = 0;

        $ePos = mb_stripos($value, 'e');

        if ($ePos !== false) {
            $exponent = (int) mb_substr($value, $ePos + 1);
            $value = mb_substr($value, 0, $ePos);
        }

        $dotPos = mb_strpos($value, '.');
        $fractionDigits = $dotPos === false ? 0 : mb_strlen($value) - $dotPos - 1;

        return max(0, $fractionDigits - $exponent);
    }
}
