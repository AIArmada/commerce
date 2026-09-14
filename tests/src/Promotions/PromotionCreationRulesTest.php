<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Actions\CreatePromotion;
use AIArmada\Promotions\Models\Promotion;

function validPromotionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Creation Rules Promotion',
        'type' => 'percentage',
        'discount_value' => 15,
    ], $overrides);
}

it('creates a promotion through the action when input is valid', function (): void {
    $promotion = app(CreatePromotion::class)->handle(validPromotionPayload(['code' => 'CREATE-OK-1']));

    expect($promotion->exists)->toBeTrue()
        ->and($promotion->code)->toBe('CREATE-OK-1');
});

it('rejects invalid promotion input', function (array $overrides): void {
    expect(fn () => app(CreatePromotion::class)->handle(validPromotionPayload($overrides)))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'missing name' => [['name' => '']],
    'unknown type' => [['type' => 'bogus']],
    'percentage above 100' => [['discount_value' => 500]],
    'negative percentage' => [['discount_value' => -5]],
    'negative fixed' => [['type' => 'fixed', 'discount_value' => -1]],
    'negative usage limit' => [['usage_limit' => -1]],
    'negative per-customer limit' => [['per_customer_limit' => -2]],
    'negative minimum amount' => [['min_purchase_amount' => -100]],
    'negative minimum quantity' => [['min_quantity' => -1]],
    'ends before starts' => [[
        'starts_at' => '2026-02-02 10:00:00',
        'ends_at' => '2026-02-01 10:00:00',
    ]],
]);

it('reuses codes across owners but rejects duplicates within one owner', function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('promotions.features.owner.auto_assign_on_create', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $first = OwnerContext::withOwner(
        $ownerA,
        fn (): Promotion => app(CreatePromotion::class)->handle(validPromotionPayload(['code' => 'SHARED-1']))
    );
    $second = OwnerContext::withOwner(
        $ownerB,
        fn (): Promotion => app(CreatePromotion::class)->handle(validPromotionPayload(['code' => 'SHARED-1']))
    );

    expect($first->getKey())->not->toBe($second->getKey());

    expect(fn (): Promotion => OwnerContext::withOwner(
        $ownerA,
        fn (): Promotion => app(CreatePromotion::class)->handle(validPromotionPayload(['code' => 'SHARED-1']))
    ))->toThrow(InvalidArgumentException::class);
});

it('requires an owner context or explicit global context when owner mode is on', function (): void {
    config()->set('promotions.features.owner.enabled', true);

    // The base test case binds a default owner; drop it to prove the guard.
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    expect(fn () => app(CreatePromotion::class)->handle(validPromotionPayload()))
        ->toThrow(NoCurrentOwnerException::class);

    $global = OwnerContext::withOwner(
        null,
        fn (): Promotion => app(CreatePromotion::class)->handle(validPromotionPayload(['code' => 'GLOBAL-1']))
    );

    expect($global->owner_type)->toBeNull()
        ->and($global->owner_id)->toBeNull();
});
