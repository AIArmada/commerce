<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;

function subscriptionRetryResponse(string $id, int $price): array
{
    return [
        'id' => $id,
        'created_on' => strtotime('2024-01-01T00:00:00Z'),
        'updated_on' => strtotime('2024-01-01T01:00:00Z'),
        'client' => ['email' => 'retry@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'total' => $price,
            'products' => [[
                'name' => 'Plan',
                'price' => $price,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'brand_retry',
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'created',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'refund_availability' => 'all',
        'refundable_amount' => $price,
        'payment_method_whitelist' => [],
    ];
}

it('returns the same two purchase ids when a keyed monthly subscription is retried', function (): void {
    $client = Mockery::mock(ChipCollectClient::class);
    $posts = 0;
    $seenKeys = [];

    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/', Mockery::any(), Mockery::on(function ($headers): bool {
            return isset($headers['Idempotency-Key']);
        }))
        ->andReturnUsing(function ($endpoint, $data, $headers) use (&$posts, &$seenKeys): array {
            $posts++;
            $seenKeys[] = $headers['Idempotency-Key'];

            $price = $data['purchase']['products'][0]['price'];

            return subscriptionRetryResponse($price === 0 ? 'trial_purchase' : 'subscription_purchase', $price);
        });

    $cache = Cache::store('array');
    $cache->clear();
    $service = new ChipCollectService($client, $cache);
    $subscriptions = new SubscriptionService($service);

    $data = [
        'client' => ['email' => 'retry@example.com'],
        'trial_days' => 14,
        'amount' => 4500,
        'brand_id' => 'brand_retry',
        'currency' => 'MYR',
        'reference' => 'sub-retry-1',
    ];

    $first = $subscriptions->createMonthlySubscription($data);
    $second = $subscriptions->createMonthlySubscription($data);

    expect($posts)->toBe(2)
        ->and($second['initial_purchase']->id)->toBe($first['initial_purchase']->id)
        ->and($second['subscription_purchase']->id)->toBe($first['subscription_purchase']->id)
        ->and($seenKeys)->toBe(['sub-retry-1-initial', 'sub-retry-1-subscription']);
});
