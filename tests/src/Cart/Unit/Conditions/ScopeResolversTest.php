<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\ConditionScopeResolverInterface;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\DefaultScopeResolver;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\FulfillmentScopeResolver;

describe('DefaultScopeResolver', function (): void {
    it('can be instantiated', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);

        expect($resolver)->toBeInstanceOf(DefaultScopeResolver::class)
            ->and($resolver)->toBeInstanceOf(ConditionScopeResolverInterface::class);
    });

    it('supports matching scope', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);

        expect($resolver->supports(ConditionScope::CART))->toBeTrue();
    });

    it('does not support non-matching scope', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);

        expect($resolver->supports(ConditionScope::ITEMS))->toBeFalse()
            ->and($resolver->supports(ConditionScope::SHIPMENTS))->toBeFalse();
    });
});

describe('FulfillmentScopeResolver', function (): void {
    it('can be instantiated', function (): void {
        $resolver = new FulfillmentScopeResolver;

        expect($resolver)->toBeInstanceOf(FulfillmentScopeResolver::class)
            ->and($resolver)->toBeInstanceOf(ConditionScopeResolverInterface::class);
    });

    it('supports fulfillments scope', function (): void {
        $resolver = new FulfillmentScopeResolver;

        expect($resolver->supports(ConditionScope::FULFILLMENTS))->toBeTrue();
    });

    it('does not support other scopes', function (): void {
        $resolver = new FulfillmentScopeResolver;

        expect($resolver->supports(ConditionScope::CART))->toBeFalse()
            ->and($resolver->supports(ConditionScope::ITEMS))->toBeFalse();
    });
});
