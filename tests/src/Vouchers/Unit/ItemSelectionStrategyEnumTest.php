<?php

declare(strict_types=1);

use AIArmada\Vouchers\Compound\Enums\ItemSelectionStrategy;

describe('ItemSelectionStrategy Enum', function (): void {
    it('has all expected strategies', function (): void {
        expect(ItemSelectionStrategy::cases())->toHaveCount(5);
        expect(ItemSelectionStrategy::Cheapest->value)->toBe('cheapest');
        expect(ItemSelectionStrategy::MostExpensive->value)->toBe('most_expensive');
        expect(ItemSelectionStrategy::First->value)->toBe('first');
        expect(ItemSelectionStrategy::Last->value)->toBe('last');
        expect(ItemSelectionStrategy::Random->value)->toBe('random');
    });

    describe('labels', function (): void {
        it('returns correct labels', function (): void {
            expect(ItemSelectionStrategy::Cheapest->label())->toBe('Cheapest Item');
            expect(ItemSelectionStrategy::MostExpensive->label())->toBe('Most Expensive Item');
            expect(ItemSelectionStrategy::First->label())->toBe('First Added');
            expect(ItemSelectionStrategy::Last->label())->toBe('Last Added');
            expect(ItemSelectionStrategy::Random->label())->toBe('Random');
        });
    });
});
