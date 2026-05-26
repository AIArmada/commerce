<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionPresets;
use AIArmada\Cart\Contracts\RulesFactoryInterface;

afterEach(function (): void {
    ConditionPresets::restoreOctaneDefaults();
});

it('restores boot-time rules factory defaults between requests', function (): void {
    ConditionPresets::rememberOctaneDefaults();
    ConditionPresets::setRulesFactory(new class implements RulesFactoryInterface
    {
        public function createRules(string $key, array $metadata = []): array
        {
            throw new RuntimeException('mutated rules factory');
        }

        public function canCreateRules(string $key): bool
        {
            return true;
        }

        public function getAvailableKeys(): array
        {
            return ['subtotal-at-least'];
        }
    });

    expect(fn () => ConditionPresets::percentageDiscountWithMinimum(10, 5000))
        ->toThrow(RuntimeException::class, 'mutated rules factory');

    ConditionPresets::restoreOctaneDefaults();

    $condition = ConditionPresets::percentageDiscountWithMinimum(10, 5000);

    expect($condition->getRules())->toBeArray()->not->toBeEmpty();
});
