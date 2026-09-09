<?php

declare(strict_types=1);

use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Support\CartMoney;

describe('CartMoney', function (): void {
    it('uses the configured cart currency as its default', function (): void {
        config()->set('cart.money.default_currency', 'myr');

        expect(CartMoney::currency())->toBe('MYR')
            ->and(CartMoney::decimalFromMinor(1250))->toBe('12.50');
    });

    it('uses configured rounding when converting decimal major units', function (): void {
        config()->set('cart.money.default_currency', 'USD');

        config()->set('cart.money.rounding_mode', 'half_up');
        expect(CartMoney::minorFromDecimal('0.025'))->toBe(3);

        config()->set('cart.money.rounding_mode', 'half_even');
        expect(CartMoney::minorFromDecimal('0.025'))->toBe(2);

        config()->set('cart.money.rounding_mode', 'floor');
        expect(CartMoney::minorFromDecimal('0.029'))->toBe(2);

        config()->set('cart.money.rounding_mode', 'ceil');
        expect(CartMoney::minorFromDecimal('0.021'))->toBe(3);
    });

    it('uses the currency precision for minor-unit conversion', function (): void {
        config()->set('cart.money.rounding_mode', 'half_up');

        expect(CartMoney::minorScale('JPY'))->toBe(1)
            ->and(CartMoney::minorFromDecimal('1.6', 'JPY'))->toBe(2)
            ->and(CartMoney::decimalFromMinor(2, 'JPY'))->toBe('2');
    });

    it('rejects unsupported rounding modes', function (): void {
        config()->set('cart.money.rounding_mode', 'bankers');

        expect(fn (): int => CartMoney::minorFromDecimal('1.00'))
            ->toThrow(InvalidArgumentException::class, 'Unsupported cart money rounding mode.');
    });

    it('initializes snapshot currency from the same model-level default', function (): void {
        config()->set('cart.money.default_currency', 'SGD');

        $snapshot = new CartSnapshot;

        expect($snapshot->getAttributes()['currency'])->toBe('SGD');
    });
});
