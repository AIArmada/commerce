<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Illuminate\Support\Facades\Cache;

/**
 * ADVERSARY PROOF — Attack 1 (prime suspect): crash-recovery double-post.
 *
 * `PurchasesApi::createIdempotently()` posts the remote purchase BEFORE
 * writing the idempotency cache (post-then-cache inside the lock callback).
 * The lock guards concurrency, not crash survival: if the process dies (or
 * the cache entry is evicted) between the POST and the `put`, a retry with
 * the same stable key re-posts and the customer is charged twice.
 *
 * Mock seam: HTTP client only (`ChipCollectClient`). The cache is the real
 * array store; the package logic under test is unmocked.
 */
function adversaryCrashPurchaseResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'purchase_crash_1',
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
    ], $overrides);
}

it('does not re-post a purchase when the idempotency cache is lost after a successful post', function (): void {
    $client = Mockery::mock(ChipCollectClient::class);
    $posts = 0;

    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/', Mockery::any())
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return adversaryCrashPurchaseResponse();
        });

    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $client);

    $requestData = [
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
        'reference' => 'adversary-crash-session-1',
    ];

    $api->create($requestData);

    // Simulate process death / cache eviction between the remote POST and
    // the idempotency-cache write: the lock is gone and nothing was stored.
    $cache->flush();

    // Retry with the identical stable key must replay, not re-post.
    $api->create($requestData);

    expect($posts)->toBe(1, 'Crash-after-post caused a second remote purchase: the customer is charged twice.');
});
