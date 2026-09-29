<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use AIArmada\Chip\Support\PurchaseIdempotencyLedger;
use Illuminate\Support\Facades\Cache;

/**
 * Deploy-boundary pins: idempotency entries stored BEFORE the wire
 * cleanup (which removed `purchase.total` and null product fields from the
 * request payload) must still match retries issued AFTER it.
 *
 * The frozen helpers below replicate the pre-cleanup fingerprint algorithm
 * exactly (recursive key sort, sha256). They must NEVER be "fixed" to match
 * the current implementation: their whole purpose is to stay old.
 */
function frozenCompatSort(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map(fn (mixed $item): mixed => frozenCompatSort($item), $value);
    }

    ksort($value);

    foreach ($value as $key => $item) {
        $value[$key] = frozenCompatSort($item);
    }

    return $value;
}

function frozenCompatFingerprint(array $data): string
{
    return hash('sha256', json_encode(frozenCompatSort($data), JSON_THROW_ON_ERROR));
}

function compatPurchasePayload(): array
{
    return [
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
            'subtotal_override' => 1000,
            'total_discount_override' => 0,
            'total_tax_override' => 0,
            'total_override' => 1000,
        ],
        'brand_id' => 'brand_123',
    ];
}

function compatPurchaseResponse(): array
{
    return [
        'id' => 'compat_purchase_1',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'total' => 1000,
            'products' => [[
                'name' => 'Item',
                'price' => 1000,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'brand_123',
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'created',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'refund_availability' => 'all',
        'refundable_amount' => 1000,
        'payment_method_whitelist' => [],
    ];
}

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
});

it('dedupes a keyed retry against a pre-cleanup object-path cache entry', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);

    $payload = compatPurchasePayload();
    $oldShape = $payload;
    $oldShape['purchase']['products'][0]['category'] = null;
    $oldShape['purchase']['total'] = 1000;

    $cacheKey = (new ReflectionMethod($api, 'idempotencyCacheKey'))->invoke($api, 'brand_123', 'compat-cache-1');
    $cache->put($cacheKey, [
        'fingerprint' => frozenCompatFingerprint($oldShape),
        'purchase' => compatPurchaseResponse(),
    ]);

    $posts = 0;
    $this->client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return compatPurchaseResponse();
        });

    $purchase = $api->create($payload, 'compat-cache-1');

    expect($posts)->toBe(0);
    expect($purchase->id)->toBe('compat_purchase_1');
});

it('dedupes a keyed retry against a pre-cleanup cents-path cache entry', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);

    // The cents/line-item builder paths never emitted the category key: their
    // pre-cleanup entries carry total WITHOUT nulls.
    $payload = compatPurchasePayload();
    $oldShape = $payload;
    $oldShape['purchase']['total'] = 1000;

    $cacheKey = (new ReflectionMethod($api, 'idempotencyCacheKey'))->invoke($api, 'brand_123', 'compat-cache-2');
    $cache->put($cacheKey, [
        'fingerprint' => frozenCompatFingerprint($oldShape),
        'purchase' => compatPurchaseResponse(),
    ]);

    $posts = 0;
    $this->client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return compatPurchaseResponse();
        });

    $purchase = $api->create($payload, 'compat-cache-2');

    expect($posts)->toBe(0);
    expect($purchase->id)->toBe('compat_purchase_1');
});

it('dedupes a keyed retry against a pre-cleanup ledger entry', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);

    $payload = compatPurchasePayload();
    $oldShape = $payload;
    $oldShape['purchase']['products'][0]['category'] = null;
    $oldShape['purchase']['total'] = 1000;

    $ledger = new PurchaseIdempotencyLedger;
    $stub = $ledger->reserve('brand_123', 'compat-ledger-1', frozenCompatFingerprint($oldShape), $payload);
    $ledger->record($stub, PurchaseData::from(compatPurchaseResponse()));

    $posts = 0;
    $this->client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return compatPurchaseResponse();
        });

    $purchase = $api->create($payload, 'compat-ledger-1');

    expect($posts)->toBe(0);
    expect($purchase->id)->toBe('compat_purchase_1');
});

it('fingerprints cleaned payloads identically to their pre-cleanup shapes', function (): void {
    $api = new PurchasesApi(null, $this->client);

    $fingerprint = new ReflectionMethod($api, 'payloadFingerprint');
    $legacy = new ReflectionMethod($api, 'legacyIdempotencyFingerprints');

    $clean = compatPurchasePayload();

    $oldCheckout = $clean;
    $oldCheckout['purchase']['products'][0]['category'] = null;

    $oldCentsBuilder = $clean;
    $oldCentsBuilder['purchase']['total'] = 1000;

    $oldObjectBuilder = $oldCheckout;
    $oldObjectBuilder['purchase']['total'] = 1000;

    // Primary: the stripped form. Pre-cleanup entries without total or nulls
    // (raw cents-style callers) match it directly, and raw callers that still
    // pass explicit nulls fold onto the same identity.
    expect($fingerprint->invoke($api, $clean))->toBe(frozenCompatFingerprint($clean));

    $withNulls = $clean;
    $withNulls['purchase']['products'][0]['category'] = null;

    expect($fingerprint->invoke($api, $withNulls))->toBe($fingerprint->invoke($api, $clean));

    // Legacy: all three removed-field combinations.
    $legacyFingerprints = $legacy->invoke($api, $clean);

    expect($legacyFingerprints)->toHaveCount(3)
        ->and($legacyFingerprints)->toContain(frozenCompatFingerprint($oldCheckout))
        ->and($legacyFingerprints)->toContain(frozenCompatFingerprint($oldCentsBuilder))
        ->and($legacyFingerprints)->toContain(frozenCompatFingerprint($oldObjectBuilder));

    // Without an override there is no total to reconstruct: only the nulls form.
    $noOverride = $clean;
    unset(
        $noOverride['purchase']['subtotal_override'],
        $noOverride['purchase']['total_discount_override'],
        $noOverride['purchase']['total_tax_override'],
        $noOverride['purchase']['total_override'],
    );

    $noOverrideNulls = $noOverride;
    $noOverrideNulls['purchase']['products'][0]['category'] = null;

    expect($legacy->invoke($api, $noOverride))->toBe([frozenCompatFingerprint($noOverrideNulls)]);
});

it('derives the frozen keyless checkout key bit-for-bit', function (): void {
    $api = new PurchasesApi(null, $this->client);

    $frozen = new ReflectionMethod($api, 'frozenCheckoutKeyFingerprint');

    // Fully literal input: no config, no defaults. If this hash moves, the
    // frozen projection moved with it — update deliberately or not at all.
    expect($frozen->invoke($api, [
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'products' => [['name' => 'Item', 'price' => 1000]],
            'currency' => 'MYR',
        ],
        'brand_id' => 'brand_123',
    ]))->toBe('c40066bbe25272ab4109da489e7d0d0141c522824fe8ed44e213825ac1afec18');
});

it('dedupes a keyless checkout retry against its pre-cleanup derived key', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);

    // Literal pre-cleanup derived key for a single MYR 1000 item checkout as
    // buyer@example.com: the key suffix doubles as the stored fingerprint
    // because pre-cleanup keys were derived from the fingerprint itself.
    $key = 'checkout-3c9a696731a22750d7a27a765d2a30eefeffeb8c3709dfdac8007741250f5e0c';
    $cacheKey = (new ReflectionMethod($api, 'idempotencyCacheKey'))->invoke($api, 'brand_test', $key);
    $cache->put($cacheKey, [
        'fingerprint' => '3c9a696731a22750d7a27a765d2a30eefeffeb8c3709dfdac8007741250f5e0c',
        'purchase' => array_replace_recursive(compatPurchaseResponse(), ['id' => 'compat_keyless_1']),
    ]);

    $this->client->shouldReceive('getBrandId')->zeroOrMoreTimes()->andReturn('brand_test');

    $posts = 0;
    $this->client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return compatPurchaseResponse();
        });

    $purchase = $api->createCheckoutPurchase(
        [ProductData::from(['name' => 'Item', 'price' => 1000, 'currency' => 'MYR'])],
        ClientDetailsData::from(['email' => 'buyer@example.com']),
    );

    expect($posts)->toBe(0);
    expect($purchase->id)->toBe('compat_keyless_1');
});

it('still rejects a reused key with a genuinely different payload', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);

    $posts = 0;
    $this->client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return compatPurchaseResponse();
        });

    $api->create(compatPurchasePayload(), 'compat-conflict-1');

    expect($posts)->toBe(1);

    $different = compatPurchasePayload();
    $different['purchase']['products'][0]['price'] = 2000;
    $different['purchase']['subtotal_override'] = 2000;
    $different['purchase']['total_override'] = 2000;

    expect(fn () => $api->create($different, 'compat-conflict-1'))
        ->toThrow(ChipValidationException::class, 'different purchase payload');
    expect($posts)->toBe(1);
});
