<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Illuminate\Support\Facades\Cache;

/**
 * ADVERSARY PROOF — Attack 1 sibling: keyless `createCheckoutPurchase()`
 * fails OPEN.
 *
 * Setup purchases fail CLOSED without a key (`createSetupPurchase` and
 * `ChipGateway::createSetupIntent` both throw). `createCheckoutPurchase()`,
 * by contrast, silently proceeds with no idempotency key when no `reference`
 * option is supplied (`PurchasesApi::create()` falls through to the
 * unprotected `postCreate()`), so a plain retry double-posts even with a
 * healthy cache. Same entrypoint class as the prime window, opposite
 * default.
 *
 * Mock seam: HTTP client only (`ChipCollectClient`).
 */

function adversaryKeylessPurchaseResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'purchase_keyless_1',
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
        'brand_id' => 'brand_test',
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

it('refuses to create a keyless checkout purchase instead of posting it unprotected', function (): void {
    $client = Mockery::mock(ChipCollectClient::class);
    $posts = 0;

    $client->shouldReceive('getBrandId')->zeroOrMoreTimes()->andReturn('brand_test');
    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/', Mockery::any())
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return adversaryKeylessPurchaseResponse();
        });

    $cache = Cache::store('array');
    $cache->clear();
    $api = new PurchasesApi($cache, $client);

    $products = [ProductData::from(['name' => 'Item', 'price' => 1000, 'currency' => 'MYR'])];
    $customer = ClientDetailsData::from(['email' => 'buyer@example.com']);

    // No `reference` option: there is no stable key, so the second call is a
    // retry of the first. A fail-closed entrypoint rejects this; here it posts.
    $api->createCheckoutPurchase($products, $customer);
    $api->createCheckoutPurchase($products, $customer);

    expect($posts)->toBe(1, 'Keyless checkout purchase posted twice with a healthy cache: retries are unprotected.');
});
