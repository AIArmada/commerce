<?php

declare(strict_types=1);

use AIArmada\Pricing\Settings\PricingSettings;
use AIArmada\Pricing\Settings\PromotionalPricingSettings;
use Spatie\LaravelSettings\Settings;

describe('PricingSettings', function (): void {
    describe('group method', function (): void {
        it('returns correct group name', function (): void {
            expect(PricingSettings::group())->toBe('pricing');
        });
    });

    describe('class structure', function (): void {
        it('extends Spatie Settings class', function (): void {
            expect(is_subclass_of(PricingSettings::class, Settings::class))->toBeTrue();
        });

        it('has expected public properties defined', function (): void {
            $reflection = new ReflectionClass(PricingSettings::class);
            $properties = array_map(fn ($p) => $p->getName(), $reflection->getProperties(ReflectionProperty::IS_PUBLIC));

            expect($properties)->toContain('defaultCurrency')
                ->and($properties)->toContain('decimalPlaces')
                ->and($properties)->toContain('pricesIncludeTax')
                ->and($properties)->toContain('roundingMode')
                ->and($properties)->toContain('minimumOrderValue')
                ->and($properties)->toContain('maximumOrderValue')
                ->and($properties)->toContain('promotionalPricingEnabled')
                ->and($properties)->toContain('tieredPricingEnabled')
                ->and($properties)->toContain('customerGroupPricingEnabled');
        });

        it('has getCurrencySymbol method', function (): void {
            expect(method_exists(PricingSettings::class, 'getCurrencySymbol'))->toBeTrue();
        });

        it('has formatAmount method', function (): void {
            expect(method_exists(PricingSettings::class, 'formatAmount'))->toBeTrue();
        });
    });

    describe('getCurrencySymbol static mapping', function (): void {
        // Test the currency symbol mapping directly via reflection
        it('has correct currency symbol mappings', function (): void {
            $reflection = new ReflectionClass(PricingSettings::class);
            $method = $reflection->getMethod('getCurrencySymbol');

            // Create a mock instance using reflection to bypass constructor
            $instance = $reflection->newInstanceWithoutConstructor();
            $defaultCurrencyProp = $reflection->getProperty('defaultCurrency');

            // Test MYR
            $defaultCurrencyProp->setValue($instance, 'MYR');
            expect($method->invoke($instance))->toBe('RM');

            // Test USD
            $defaultCurrencyProp->setValue($instance, 'USD');
            expect($method->invoke($instance))->toBe('$');

            // Test EUR
            $defaultCurrencyProp->setValue($instance, 'EUR');
            expect($method->invoke($instance))->toBe('€');

            // Test GBP
            $defaultCurrencyProp->setValue($instance, 'GBP');
            expect($method->invoke($instance))->toBe('£');

            // Test SGD
            $defaultCurrencyProp->setValue($instance, 'SGD');
            expect($method->invoke($instance))->toBe('$');

            // Test THB (known to Akaunting Money)
            $defaultCurrencyProp->setValue($instance, 'THB');
            expect($method->invoke($instance))->toBe('฿');
        });
    });

    describe('formatAmount via reflection', function (): void {
        it('formats amount correctly', function (): void {
            $reflection = new ReflectionClass(PricingSettings::class);
            $method = $reflection->getMethod('formatAmount');

            // Create instance without constructor
            $instance = $reflection->newInstanceWithoutConstructor();

            $defaultCurrencyProp = $reflection->getProperty('defaultCurrency');
            $defaultCurrencyProp->setValue($instance, 'MYR');

            $decimalPlacesProp = $reflection->getProperty('decimalPlaces');
            $decimalPlacesProp->setValue($instance, 2);

            // Test formatting
            expect($method->invoke($instance, 10000))->toBe('RM100.00');
            expect($method->invoke($instance, 9999))->toBe('RM99.99');
            expect($method->invoke($instance, 1))->toBe('RM0.01');
            expect($method->invoke($instance, 0))->toBe('RM0.00');
        });

        it('handles large amounts', function (): void {
            $reflection = new ReflectionClass(PricingSettings::class);
            $method = $reflection->getMethod('formatAmount');

            $instance = $reflection->newInstanceWithoutConstructor();

            $defaultCurrencyProp = $reflection->getProperty('defaultCurrency');
            $defaultCurrencyProp->setValue($instance, 'MYR');

            $decimalPlacesProp = $reflection->getProperty('decimalPlaces');
            $decimalPlacesProp->setValue($instance, 2);

            expect($method->invoke($instance, 100000000))->toBe('RM1,000,000.00');
        });

        it('handles zero decimal currencies', function (): void {
            $reflection = new ReflectionClass(PricingSettings::class);
            $method = $reflection->getMethod('formatAmount');

            $instance = $reflection->newInstanceWithoutConstructor();

            $defaultCurrencyProp = $reflection->getProperty('defaultCurrency');
            $defaultCurrencyProp->setValue($instance, 'JPY');

            $decimalPlacesProp = $reflection->getProperty('decimalPlaces');
            $decimalPlacesProp->setValue($instance, 0);

            expect($method->invoke($instance, 100))->toBe('¥100');
        });
    });
});

describe('PromotionalPricingSettings', function (): void {
    describe('group method', function (): void {
        it('returns correct group name', function (): void {
            expect(PromotionalPricingSettings::group())->toBe('pricing_promotional');
        });
    });

    describe('class structure', function (): void {
        it('extends Spatie Settings class', function (): void {
            expect(is_subclass_of(PromotionalPricingSettings::class, Settings::class))->toBeTrue();
        });

        it('has expected public properties defined', function (): void {
            $reflection = new ReflectionClass(PromotionalPricingSettings::class);
            $properties = array_map(fn ($p) => $p->getName(), $reflection->getProperties(ReflectionProperty::IS_PUBLIC));

            expect($properties)->toContain('flashSalesEnabled')
                ->and($properties)->toContain('defaultFlashSaleDurationHours')
                ->and($properties)->toContain('maxDiscountPercentage')
                ->and($properties)->toContain('allowPromotionStacking')
                ->and($properties)->toContain('maxStackablePromotions')
                ->and($properties)->toContain('showOriginalPrice')
                ->and($properties)->toContain('showCountdownTimers');
        });
    });
});
