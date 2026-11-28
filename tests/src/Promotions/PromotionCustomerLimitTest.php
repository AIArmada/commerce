<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Services\PromotionService;
use Illuminate\Support\Facades\DB;

function orderWithPromotionAllocation(User $customer, Promotion $promotion, ?User $owner = null, mixed $amount = 1000): Order
{
    $attributes = [
        'customer_id' => $customer->getKey(),
        'metadata' => [
            'discount_data' => [
                'allocations' => [[
                    'provider_key' => 'promotions',
                    'requested_amount' => $amount,
                    'meta' => ['promotion_id' => $promotion->getKey()],
                ]],
            ],
        ],
    ];

    if ($owner !== null) {
        return OwnerContext::withOwner($owner, fn (): Order => Order::factory()->create($attributes));
    }

    return Order::factory()->create($attributes);
}

it('denies limited promotions to guests instead of failing open', function (): void {
    $promotion = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    $matches = app(PromotionService::class)->getApplicablePromotions(new TargetingContext(null));

    expect($matches->pluck('id')->all())->not->toContain($promotion->getKey());
});

it('still applies limit-free promotions to guests', function (): void {
    $promotion = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => null,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    $matches = app(PromotionService::class)->getApplicablePromotions(new TargetingContext(null));

    expect($matches->pluck('id')->all())->toContain($promotion->getKey());
});

it('enforces per-customer limits across several promotions with one order scan', function (): void {
    $customer = User::factory()->create();

    $capped = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);
    $roomy = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 5,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);
    $otherCapped = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    orderWithPromotionAllocation($customer, $capped);

    DB::enableQueryLog();

    try {
        $matches = app(PromotionService::class)
            ->getApplicablePromotions(new TargetingContext(null, $customer));
    } finally {
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
    }

    $orderScans = collect($queries)->filter(
        static fn (array $query): bool => str_contains($query['query'], 'orders')
    );

    expect($matches->pluck('id')->all())
        ->not->toContain($capped->getKey())
        ->toContain($roomy->getKey())
        ->toContain($otherCapped->getKey())
        ->and($orderScans->count())->toBe(1);
});

it('counts multiple allocations of the same promotion as one customer redemption per order', function (): void {
    $customer = User::factory()->create();
    $promotion = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 2,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    $allocation = [
        'provider_key' => 'promotions',
        'requested_amount' => 1000,
        'meta' => ['promotion_id' => $promotion->getKey()],
    ];

    Order::factory()->create([
        'customer_id' => $customer->getKey(),
        'metadata' => [
            'discount_data' => [
                'allocations' => [
                    $allocation,
                    $allocation,
                    ['provider_key' => 'vouchers', 'requested_amount' => 1000, 'meta' => ['promotion_id' => $promotion->getKey()]],
                    ['provider_key' => 'promotions', 'requested_amount' => 1000, 'meta' => ['promotion_id' => ['invalid']]],
                ],
            ],
        ],
    ]);

    $service = app(PromotionService::class);
    $context = new TargetingContext(null, $customer);

    expect($service->getApplicablePromotions($context)->pluck('id')->all())
        ->toContain($promotion->getKey());

    orderWithPromotionAllocation($customer, $promotion);

    expect($service->getApplicablePromotions($context)->pluck('id')->all())
        ->not->toContain($promotion->getKey());
});

it('only counts canonical checkout allocations towards a customer limit', function (): void {
    $customer = User::factory()->create();
    $promotion = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    Order::factory()->create([
        'customer_id' => $customer->getKey(),
        'metadata' => [
            'allocations' => [[
                'provider_key' => 'promotions',
                'requested_amount' => 1000,
                'meta' => ['promotion_id' => $promotion->getKey()],
            ]],
        ],
    ]);

    $service = app(PromotionService::class);
    $context = new TargetingContext(null, $customer);

    expect($service->getApplicablePromotions($context)->pluck('id')->all())
        ->toContain($promotion->getKey());

    orderWithPromotionAllocation($customer, $promotion);

    expect($service->getApplicablePromotions($context)->pluck('id')->all())
        ->not->toContain($promotion->getKey());
});

it('denies customer-limited promotions when the customer identifier is malformed', function (): void {
    $promotion = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    $context = new TargetingContext(null, metadata: ['customer_id' => []]);

    expect(app(PromotionService::class)->getApplicablePromotions($context)->pluck('id')->all())
        ->not->toContain($promotion->getKey());
});

it('ignores invalid allocated amounts when calculating customer promotion usage', function (mixed $amount): void {
    $customer = User::factory()->create();
    $promotion = Promotion::factory()->automatic()->active()->create([
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'usage_limit' => null,
    ]);

    orderWithPromotionAllocation($customer, $promotion, amount: $amount);

    $service = app(PromotionService::class);
    $context = new TargetingContext(null, $customer);

    expect($service->getApplicablePromotions($context)->pluck('id')->all())
        ->toContain($promotion->getKey());

    orderWithPromotionAllocation($customer, $promotion);

    expect($service->getApplicablePromotions($context)->pluck('id')->all())
        ->not->toContain($promotion->getKey());
})->with([0, -1, '1000', null, 1.5, true]);
