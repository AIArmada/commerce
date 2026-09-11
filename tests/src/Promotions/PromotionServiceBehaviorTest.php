<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Services\PromotionService;
use Carbon\CarbonImmutable;

it('normalizes promotion codes on write and resolves them exactly', function (): void {
    $promotion = Promotion::create([
        'name' => 'Normalized Code',
        'code' => '  save10  ',
        'discount_value' => 10,
        'is_active' => true,
    ]);

    $context = new TargetingContext(null);

    expect($promotion->code)->toBe('SAVE10')
        ->and(app(PromotionService::class)->findApplicableCodePromotion(' sAvE10 ', $context)?->getKey())
        ->toBe($promotion->getKey());
});

it('keeps the default promotion evaluation byte-identical to the additive as-of path', function (): void {
    $promotion = Promotion::factory()->automatic()->active()->create([
        'name' => 'Parity Promotion',
        'discount_value' => 10,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'per_customer_limit' => null,
    ]);
    $context = new TargetingContext(null);
    $service = app(PromotionService::class);

    $default = $service->getApplicablePromotions($context)->pluck('id')->all();
    $asOf = $service->getApplicablePromotionsAsOf($context, CarbonImmutable::now())->pluck('id')->all();

    expect($default)->toBe($asOf)->toContain($promotion->getKey());
});

it('evaluates promotions at the supplied instant', function (): void {
    $startsAt = CarbonImmutable::parse('2025-01-02 10:00:00');
    $endsAt = $startsAt->addHour();
    $promotion = Promotion::factory()->automatic()->active()->create([
        'name' => 'Historical Promotion',
        'discount_value' => 10,
        'min_purchase_amount' => null,
        'min_quantity' => null,
        'per_customer_limit' => null,
        'usage_limit' => null,
        'usage_count' => 0,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
    $context = new TargetingContext(null);
    $service = app(PromotionService::class);

    expect($service->getApplicablePromotionsAsOf($context, $startsAt->subSecond())->pluck('id')->all())
        ->not->toContain($promotion->getKey())
        ->and($service->getApplicablePromotionsAsOf($context, $startsAt)->pluck('id')->all())
        ->toContain($promotion->getKey())
        ->and($service->getApplicablePromotionsAsOf($context, $endsAt->addSecond())->pluck('id')->all())
        ->not->toContain($promotion->getKey());
});

it('enforces a per-customer limit from owner-scoped order allocations', function (): void {
    $owner = User::factory()->create();
    $customer = User::factory()->create();

    $promotion = OwnerContext::withOwner($owner, fn (): Promotion => Promotion::create([
        'name' => 'Customer Capped Promotion',
        'discount_value' => 10,
        'per_customer_limit' => 1,
        'is_active' => true,
    ]));

    OwnerContext::withOwner($owner, fn () => Order::factory()->paid()->create([
        'customer_id' => $customer->getKey(),
        'metadata' => [
            'discount_data' => [
                'allocations' => [[
                    'provider_key' => 'promotions',
                    'meta' => ['promotion_id' => $promotion->getKey()],
                ]],
            ],
        ],
    ]));

    $matches = OwnerContext::withOwner($owner, fn () => app(PromotionService::class)
        ->getApplicablePromotions(new TargetingContext(null, $customer)));

    expect($matches)->toBeEmpty();
});

it('atomically rejects usage increments after the promotion limit', function (): void {
    $promotion = Promotion::create([
        'name' => 'Atomic Limit',
        'discount_value' => 10,
        'usage_limit' => 1,
        'usage_count' => 0,
        'is_active' => true,
    ]);

    expect($promotion->tryIncrementUsage())->toBeTrue()
        ->and($promotion->tryIncrementUsage())->toBeFalse()
        ->and($promotion->fresh()->usage_count)->toBe(1);
});

it('does not silently ignore a rejected increment through the legacy model method', function (): void {
    $promotion = Promotion::create([
        'name' => 'Rejected Increment',
        'discount_value' => 10,
        'usage_limit' => 1,
        'is_active' => true,
    ]);

    $promotion->incrementUsage();

    expect(fn () => $promotion->incrementUsage())
        ->toThrow(LogicException::class);
});
