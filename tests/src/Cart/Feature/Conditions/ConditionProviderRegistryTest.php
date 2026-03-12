<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Contracts\ConditionProviderInterface;
use AIArmada\Cart\Facades\Cart;

it('syncs condition providers into cart conditions', function (): void {
    $registry = app(ConditionProviderRegistry::class);

    $provider = new class implements ConditionProviderInterface
    {
        public function getConditionsFor(AIArmada\Cart\Cart $cart): array
        {
            return [new CartCondition(
                name: 'test_provider_condition',
                type: 'test',
                target: [
                    'scope' => ConditionScope::CART->value,
                    'phase' => ConditionPhase::CART_SUBTOTAL->value,
                    'application' => ConditionApplication::AGGREGATE->value,
                ],
                value: 100,
                attributes: ['source' => 'test'],
                order: 10,
            )];
        }

        public function validate(CartCondition $condition, AIArmada\Cart\Cart $cart): bool
        {
            return true;
        }

        public function getType(): string
        {
            return 'test';
        }

        public function getPriority(): int
        {
            return 10;
        }
    };

    $registry->register($provider);

    $conditions = Cart::getConditions();
    $condition = $conditions->get('test_provider_condition');

    expect($condition)->not()->toBeNull()
        ->and($condition->getAttribute('__provider'))->toBe($provider::class);
});
