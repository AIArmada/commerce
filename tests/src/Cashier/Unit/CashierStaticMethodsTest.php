<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;

uses(CashierTestCase::class);

describe('Cashier Static Methods Full Coverage', function (): void {
    afterEach(function (): void {
        Cashier::restoreOctaneDefaults();
    });

    describe('deactivatePastDue', function (): void {
        it('sets deactivatePastDue to true by default', function (): void {
            Cashier::$deactivatePastDue = false;

            Cashier::deactivatePastDue();

            expect(Cashier::$deactivatePastDue)->toBeTrue();
        });

        /* Explicit false + unverified syncs removed; covered by CashierTest. */
    });

    describe('deactivateIncomplete', function (): void {
        it('sets deactivateIncomplete to true by default', function (): void {
            Cashier::$deactivateIncomplete = false;

            Cashier::deactivateIncomplete();

            expect(Cashier::$deactivateIncomplete)->toBeTrue();
        });

        /* Explicit false + unverified syncs removed; covered by CashierTest. */
    });

    describe('defaultCurrency', function (): void {
        it('returns configured default currency', function (): void {
            config(['cashier.currency' => 'EUR']);

            expect(Cashier::defaultCurrency())->toBe('EUR');
        });

        it('returns USD as default when not configured', function (): void {
            $cashierConfig = config('cashier', []);
            unset($cashierConfig['currency']);

            config(['cashier' => $cashierConfig]);

            expect(Cashier::defaultCurrency())->toBe('USD');
        });
    });

    describe('defaultGateway', function (): void {
        it('returns configured default gateway', function (): void {
            config(['cashier.default' => 'chip']);

            expect(Cashier::defaultGateway())->toBe('chip');
        });
    });

    describe('availableGateways', function (): void {
        it('returns array of gateway names', function (): void {
            config([
                'cashier.gateways' => [
                    'stripe' => ['driver' => 'stripe'],
                    'chip' => ['driver' => 'chip'],
                ],
            ]);

            $gateways = Cashier::availableGateways();

            expect($gateways)->toBe(['stripe', 'chip']);
        });

        it('returns empty array when no gateways configured', function (): void {
            config(['cashier.gateways' => []]);

            $gateways = Cashier::availableGateways();

            expect($gateways)->toBe([]);
        });
    });

    describe('supportedGateways', function (): void {
        it('is alias for availableGateways', function (): void {
            config([
                'cashier.gateways' => [
                    'stripe' => ['driver' => 'stripe'],
                    'chip' => ['driver' => 'chip'],
                ],
            ]);

            expect(Cashier::supportedGateways())->toBe(Cashier::availableGateways());
        });
    });

    /* useCustomerModel removed; covered by CashierTest. */

    describe('formatCurrencyUsing', function (): void {
        afterEach(function (): void {
            Cashier::restoreOctaneDefaults();
        });

        it('allows setting custom currency formatter', function (): void {
            Cashier::formatCurrencyUsing(function ($amount, $currency, $locale) {
                return "CUSTOM: {$currency} " . ($amount / 100);
            });

            $formatted = Cashier::formatAmount(1000, 'USD');

            expect($formatted)->toBe('CUSTOM: USD 10');
        });

        it('passes all parameters to custom formatter', function (): void {
            $receivedAmount = null;
            $receivedCurrency = null;
            $receivedLocale = null;

            Cashier::formatCurrencyUsing(function ($amount, $currency, $locale) use (&$receivedAmount, &$receivedCurrency, &$receivedLocale) {
                $receivedAmount = $amount;
                $receivedCurrency = $currency;
                $receivedLocale = $locale;

                return 'test';
            });

            config(['cashier.locale' => 'de_DE']);
            Cashier::formatAmount(2500, 'EUR', 'de_DE');

            expect($receivedAmount)->toBe(2500)
                ->and($receivedCurrency)->toBe('EUR')
                ->and($receivedLocale)->toBe('de_DE');
        });
    });

    describe('formatAmount', function (): void {
        it('uses default currency when not specified', function (): void {
            config([
                'cashier.currency' => 'USD',
                'cashier.locale' => 'en_US',
            ]);

            $formatted = Cashier::formatAmount(1000);

            expect($formatted)->toBeString();
        });

        it('uses specified currency', function (): void {
            config(['cashier.locale' => 'en_US']);

            $formatted = Cashier::formatAmount(1000, 'eur');

            expect($formatted)->toBeString();
        });

        it('uses default locale when not specified', function (): void {
            config([
                'cashier.currency' => 'USD',
                'cashier.locale' => 'en_US',
            ]);

            $formatted = Cashier::formatAmount(1000, 'USD');

            expect($formatted)->toBeString();
        });

        it('uses app locale as fallback', function (): void {
            config([
                'cashier.locale' => 'en',
                'app.locale' => 'en',
            ]);

            $formatted = Cashier::formatAmount(1000, 'USD', 'en');

            expect($formatted)->toBeString();
        });

        it('uppercases currency code with custom formatter', function (): void {
            // Note: When custom formatter is set, it receives the currency BEFORE uppercasing
            // This is intentional - the formatter can handle casing itself
            Cashier::formatCurrencyUsing(function ($amount, $currency, $locale) {
                return mb_strtoupper($currency); // Uppercase in formatter
            });

            $result = Cashier::formatAmount(1000, 'eur');

            expect($result)->toBe('EUR');
        });
    });

    describe('Octane defaults', function (): void {
        it('restores boot-time static configuration between requests', function (): void {
            Cashier::useCustomerModel('App\\Models\\Customer');
            Cashier::deactivatePastDue(false);
            Cashier::deactivateIncomplete(false);
            Cashier::formatCurrencyUsing(fn ($amount, $currency, $locale) => 'mutated');

            Cashier::restoreOctaneDefaults();

            expect(Cashier::$customerModel)->toBe(User::class)
                ->and(Cashier::$deactivatePastDue)->toBeTrue()
                ->and(Cashier::$deactivateIncomplete)->toBeTrue()
                ->and(Cashier::formatAmount(1000, 'USD', 'en_US'))->not->toBe('mutated');
        });
    });
});
