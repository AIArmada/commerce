<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Cashier Class - Additional Coverage', function (): void {
    beforeEach(function (): void {
        // Reset static properties
    });

    describe('gateway', function (): void {
        it('returns gateway via static method', function (): void {
            $gateway = Cashier::gateway();

            expect($gateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('returns specific gateway via static method', function (): void {
            $gateway = Cashier::gateway('chip');

            expect($gateway->name())->toBe('chip');
        });
    });

    /* manager/supportedGateways/formatCurrencyUsing removed; covered by CashierTest + CashierStaticMethodsTest. */

    describe('formatAmount', function (): void {
        it('formats native minor units without multiplying them again', function (): void {
            Cashier::formatCurrencyUsing(null);

            expect(Cashier::formatAmount(1000, 'MYR'))->toBe('RM10.00');
        });

        it('formats amount with default currency', function (): void {
            // Use a fresh formatter
            Cashier::formatCurrencyUsing(fn ($a, $c, $l) => '$' . number_format($a / 100, 2));

            $formatted = Cashier::formatAmount(1000);

            expect($formatted)->toBe('$10.00');
        });

        it('formats amount with specific currency', function (): void {
            Cashier::formatCurrencyUsing(fn ($a, $c, $l) => $c . ' ' . number_format($a / 100, 2));

            $formatted = Cashier::formatAmount(5000, 'MYR');

            expect($formatted)->toBe('MYR 50.00');
        });

        it('formats amount with specific locale', function (): void {
            Cashier::formatCurrencyUsing(fn ($a, $c, $l) => "[$l] $c $a");

            $formatted = Cashier::formatAmount(10000, 'USD', 'en_US');

            expect($formatted)->toBe('[en_US] USD 10000');
        });
    });

    /* deactivate + useCustomerModel setters removed; covered by CashierTest true+false matrix. */
});
