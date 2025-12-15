<?php

declare(strict_types=1);

use AIArmada\Vouchers\Compound\Enums\ItemSelectionStrategy;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;

describe('ItemSelectionStrategy enum', function (): void {
    describe('values', function (): void {
        it('has Cheapest strategy', function (): void {
            expect(ItemSelectionStrategy::Cheapest->value)->toBe('cheapest');
        });

        it('has MostExpensive strategy', function (): void {
            expect(ItemSelectionStrategy::MostExpensive->value)->toBe('most_expensive');
        });

        it('has First strategy', function (): void {
            expect(ItemSelectionStrategy::First->value)->toBe('first');
        });

        it('has Last strategy', function (): void {
            expect(ItemSelectionStrategy::Last->value)->toBe('last');
        });

        it('has Random strategy', function (): void {
            expect(ItemSelectionStrategy::Random->value)->toBe('random');
        });
    });

    describe('labels', function (): void {
        it('returns label for Cheapest', function (): void {
            expect(ItemSelectionStrategy::Cheapest->label())->toBe('Cheapest Item');
        });

        it('returns label for MostExpensive', function (): void {
            expect(ItemSelectionStrategy::MostExpensive->label())->toBe('Most Expensive Item');
        });

        it('returns label for First', function (): void {
            expect(ItemSelectionStrategy::First->label())->toBe('First Added');
        });

        it('returns label for Last', function (): void {
            expect(ItemSelectionStrategy::Last->label())->toBe('Last Added');
        });

        it('returns label for Random', function (): void {
            expect(ItemSelectionStrategy::Random->label())->toBe('Random');
        });
    });

    describe('descriptions', function (): void {
        it('returns description for Cheapest', function (): void {
            expect(ItemSelectionStrategy::Cheapest->description())
                ->toBe('Select the cheapest matching items for the discount');
        });

        it('returns description for MostExpensive', function (): void {
            expect(ItemSelectionStrategy::MostExpensive->description())
                ->toBe('Select the most expensive matching items for the discount');
        });

        it('returns description for First', function (): void {
            expect(ItemSelectionStrategy::First->description())
                ->toBe('Select items in the order they were added to cart');
        });

        it('returns description for Last', function (): void {
            expect(ItemSelectionStrategy::Last->description())
                ->toBe('Select the most recently added items first');
        });

        it('returns description for Random', function (): void {
            expect(ItemSelectionStrategy::Random->description())
                ->toBe('Select items randomly');
        });
    });

    describe('tryFrom', function (): void {
        it('can create from string value', function (): void {
            expect(ItemSelectionStrategy::tryFrom('cheapest'))->toBe(ItemSelectionStrategy::Cheapest);
            expect(ItemSelectionStrategy::tryFrom('most_expensive'))->toBe(ItemSelectionStrategy::MostExpensive);
            expect(ItemSelectionStrategy::tryFrom('first'))->toBe(ItemSelectionStrategy::First);
            expect(ItemSelectionStrategy::tryFrom('last'))->toBe(ItemSelectionStrategy::Last);
            expect(ItemSelectionStrategy::tryFrom('random'))->toBe(ItemSelectionStrategy::Random);
        });

        it('returns null for invalid value', function (): void {
            expect(ItemSelectionStrategy::tryFrom('invalid'))->toBeNull();
        });
    });

    describe('cases', function (): void {
        it('has 5 cases', function (): void {
            expect(ItemSelectionStrategy::cases())->toHaveCount(5);
        });
    });
});

describe('ProductMatcherType enum', function (): void {
    describe('values', function (): void {
        it('has Sku type', function (): void {
            expect(ProductMatcherType::Sku->value)->toBe('sku');
        });

        it('has Category type', function (): void {
            expect(ProductMatcherType::Category->value)->toBe('category');
        });

        it('has Price type', function (): void {
            expect(ProductMatcherType::Price->value)->toBe('price');
        });

        it('has Attribute type', function (): void {
            expect(ProductMatcherType::Attribute->value)->toBe('attribute');
        });

        it('has All type', function (): void {
            expect(ProductMatcherType::All->value)->toBe('all');
        });

        it('has Any type', function (): void {
            expect(ProductMatcherType::Any->value)->toBe('any');
        });
    });

    describe('labels', function (): void {
        it('returns label for Sku', function (): void {
            expect(ProductMatcherType::Sku->label())->toBe('SKU Match');
        });

        it('returns label for Category', function (): void {
            expect(ProductMatcherType::Category->label())->toBe('Category Match');
        });

        it('returns label for Price', function (): void {
            expect(ProductMatcherType::Price->label())->toBe('Price Range');
        });

        it('returns label for Attribute', function (): void {
            expect(ProductMatcherType::Attribute->label())->toBe('Attribute Match');
        });

        it('returns label for All', function (): void {
            expect(ProductMatcherType::All->label())->toBe('Match All (AND)');
        });

        it('returns label for Any', function (): void {
            expect(ProductMatcherType::Any->label())->toBe('Match Any (OR)');
        });
    });

    describe('descriptions', function (): void {
        it('returns description for Sku', function (): void {
            expect(ProductMatcherType::Sku->description())
                ->toBe('Match products by SKU/product ID');
        });

        it('returns description for Category', function (): void {
            expect(ProductMatcherType::Category->description())
                ->toBe('Match products by category');
        });

        it('returns description for Price', function (): void {
            expect(ProductMatcherType::Price->description())
                ->toBe('Match products within a price range');
        });

        it('returns description for Attribute', function (): void {
            expect(ProductMatcherType::Attribute->description())
                ->toBe('Match products by custom attribute');
        });

        it('returns description for All', function (): void {
            expect(ProductMatcherType::All->description())
                ->toBe('All conditions must match');
        });

        it('returns description for Any', function (): void {
            expect(ProductMatcherType::Any->description())
                ->toBe('Any condition can match');
        });
    });

    describe('isComposite', function (): void {
        it('returns true for All', function (): void {
            expect(ProductMatcherType::All->isComposite())->toBeTrue();
        });

        it('returns true for Any', function (): void {
            expect(ProductMatcherType::Any->isComposite())->toBeTrue();
        });

        it('returns false for Sku', function (): void {
            expect(ProductMatcherType::Sku->isComposite())->toBeFalse();
        });

        it('returns false for Category', function (): void {
            expect(ProductMatcherType::Category->isComposite())->toBeFalse();
        });

        it('returns false for Price', function (): void {
            expect(ProductMatcherType::Price->isComposite())->toBeFalse();
        });

        it('returns false for Attribute', function (): void {
            expect(ProductMatcherType::Attribute->isComposite())->toBeFalse();
        });
    });

    describe('tryFrom', function (): void {
        it('can create from string value', function (): void {
            expect(ProductMatcherType::tryFrom('sku'))->toBe(ProductMatcherType::Sku);
            expect(ProductMatcherType::tryFrom('category'))->toBe(ProductMatcherType::Category);
            expect(ProductMatcherType::tryFrom('price'))->toBe(ProductMatcherType::Price);
            expect(ProductMatcherType::tryFrom('attribute'))->toBe(ProductMatcherType::Attribute);
            expect(ProductMatcherType::tryFrom('all'))->toBe(ProductMatcherType::All);
            expect(ProductMatcherType::tryFrom('any'))->toBe(ProductMatcherType::Any);
        });

        it('returns null for invalid value', function (): void {
            expect(ProductMatcherType::tryFrom('invalid'))->toBeNull();
        });
    });

    describe('cases', function (): void {
        it('has 6 cases', function (): void {
            expect(ProductMatcherType::cases())->toHaveCount(6);
        });
    });
});
