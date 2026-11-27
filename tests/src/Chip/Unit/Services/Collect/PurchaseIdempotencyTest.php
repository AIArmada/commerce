<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Drivers\ChipCollectClient;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Collect\Actions\PurchasesApi;
use AIArmada\Chip\Features\Collect\Persistence\PurchaseIdempotencyLedger;
use Illuminate\Support\Facades\Cache;

function canonicalTestSort(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map(fn (mixed $item): mixed => canonicalTestSort($item), $value);
    }

    ksort($value);

    foreach ($value as $key => $item) {
        $value[$key] = canonicalTestSort($item);
    }

    return $value;
}

function canonicalTestFingerprint(array $data): string
{
    return hash('sha256', json_encode(canonicalTestSort($data), JSON_THROW_ON_ERROR));
}

function idempotencyTestPayload(): array
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

function idempotencyTestResponse(): array
{
    return [
        'id' => 'canonical_purchase_1',
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

it('replays the canonical cached purchase without another provider request', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);
    $payload = idempotencyTestPayload();
    $key = 'canonical-cache-1';
    $cacheKey = (new ReflectionMethod($api, 'idempotencyCacheKey'))->invoke($api, 'brand_123', $key);
    $cache->put($cacheKey, [
        'fingerprint' => canonicalTestFingerprint($payload),
        'purchase' => idempotencyTestResponse(),
    ]);
    $this->client->shouldNotReceive('post');

    expect($api->create($payload, $key)->id)->toBe('canonical_purchase_1');
});

it('replays a canonical durable purchase without another provider request', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);
    $payload = idempotencyTestPayload();
    $ledger = new PurchaseIdempotencyLedger;
    $stub = $ledger->reserve('brand_123', 'canonical-ledger-1', canonicalTestFingerprint($payload), $payload);
    $ledger->record($stub, PurchaseData::from(idempotencyTestResponse()));
    $this->client->shouldNotReceive('post');

    expect($api->create($payload, 'canonical-ledger-1')->id)->toBe('canonical_purchase_1');
});

it('fails closed on an incompatible stored fingerprint instead of creating another purchase', function (): void {
    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $this->client);
    $payload = idempotencyTestPayload();
    $key = 'incompatible-fingerprint';
    $cacheKey = (new ReflectionMethod($api, 'idempotencyCacheKey'))->invoke($api, 'brand_123', $key);
    $cache->put($cacheKey, [
        'fingerprint' => hash('sha256', 'different payload'),
        'purchase' => idempotencyTestResponse(),
    ]);
    $this->client->shouldNotReceive('post');

    expect(fn () => $api->create($payload, $key))
        ->toThrow(ChipValidationException::class, 'different purchase payload');
});

it('keeps the checkout key projection deterministic and independent of request serialization', function (): void {
    $api = new PurchasesApi(null, $this->client);
    $projection = new ReflectionMethod($api, 'checkoutKeyFingerprint');

    expect($projection->invoke($api, [
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'products' => [['name' => 'Item', 'price' => 1000]],
            'currency' => 'MYR',
        ],
        'brand_id' => 'brand_123',
    ]))->toBe('c40066bbe25272ab4109da489e7d0d0141c522824fe8ed44e213825ac1afec18');
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

            return idempotencyTestResponse();
        });

    $api->create(idempotencyTestPayload(), 'canonical-conflict-1');

    expect($posts)->toBe(1);

    $different = idempotencyTestPayload();
    $different['purchase']['products'][0]['price'] = 2000;
    $different['purchase']['subtotal_override'] = 2000;
    $different['purchase']['total_override'] = 2000;

    expect(fn () => $api->create($different, 'canonical-conflict-1'))
        ->toThrow(ChipValidationException::class, 'different purchase payload');
    expect($posts)->toBe(1);
});
