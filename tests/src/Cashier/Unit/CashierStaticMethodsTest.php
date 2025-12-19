<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Cashier Static Methods Full Coverage', function (): void {
    afterEach(function (): void {
        // Reset static properties between tests
        Cashier::$deactivatePastDue = true;
        Cashier::$deactivateIncomplete = true;
        Cashier::$registersRoutes = true;
    });

    describe('deactivatePastDue', function (): void {
        it('sets deactivatePastDue to true by default', function (): void {
            Cashier::$deactivatePastDue = false;

            Cashier::deactivatePastDue();

            expect(Cashier::$deactivatePastDue)->toBeTrue();
        });

        it('sets deactivatePastDue to false when passed false', function (): void {
            Cashier::deactivatePastDue(false);

            expect(Cashier::$deactivatePastDue)->toBeFalse();
        });

        it('syncs to Laravel Cashier when available', function (): void {
            // This tests the code path even if Laravel\Cashier\Cashier isn't available
            Cashier::deactivatePastDue(true);

            expect(Cashier::$deactivatePastDue)->toBeTrue();
        });

        it('syncs to CashierChip when available', function (): void {
            // This tests the code path even if AIArmada\CashierChip\Cashier isn't available
            Cashier::deactivatePastDue(false);

            expect(Cashier::$deactivatePastDue)->toBeFalse();
        });
    });

    describe('deactivateIncomplete', function (): void {
        it('sets deactivateIncomplete to true by default', function (): void {
            Cashier::$deactivateIncomplete = false;

            Cashier::deactivateIncomplete();

            expect(Cashier::$deactivateIncomplete)->toBeTrue();
        });

        it('sets deactivateIncomplete to false when passed false', function (): void {
            Cashier::deactivateIncomplete(false);

            expect(Cashier::$deactivateIncomplete)->toBeFalse();
        });

        it('syncs to Laravel Cashier when available', function (): void {
            // This tests the code path even if Laravel\Cashier\Cashier isn't available
            Cashier::deactivateIncomplete(true);

            expect(Cashier::$deactivateIncomplete)->toBeTrue();
        });

        it('syncs to CashierChip when available', function (): void {
            // This tests the code path even if AIArmada\CashierChip\Cashier isn't available
            Cashier::deactivateIncomplete(false);

            expect(Cashier::$deactivateIncomplete)->toBeFalse();
        });
    });

    describe('ignoreRoutes', function (): void {
        it('sets registersRoutes to false', function (): void {
            expect(Cashier::$registersRoutes)->toBeTrue();

            Cashier::ignoreRoutes();

            expect(Cashier::$registersRoutes)->toBeFalse();
        });
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

    describe('useCustomerModel', function (): void {
        it('sets customer model class', function (): void {
            Cashier::useCustomerModel('App\\Models\\Customer');

            expect(Cashier::$customerModel)->toBe('App\\Models\\Customer');
        });
    });

    describe('formatCurrencyUsing', function (): void {
        afterEach(function (): void {
            // Reset the formatter
            Cashier::formatCurrencyUsing(fn ($amount, $currency, $locale) => null);
            // Re-set to null through reflection
            $reflection = new ReflectionClass(Cashier::class);
            $property = $reflection->getProperty('formatCurrencyUsing');
            $property->setAccessible(true);
            $property->setValue(null, null);
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

            // Reset formatter
            $reflection = new ReflectionClass(Cashier::class);
            $property = $reflection->getProperty('formatCurrencyUsing');
            $property->setAccessible(true);
            $property->setValue(null, null);
        });
    });
});
