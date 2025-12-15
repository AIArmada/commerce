<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use AIArmada\Cashier\Contracts\GatewayContract;
use Akaunting\Money\Money;
use Closure;

/**
 * Main Cashier class for multi-gateway payment management.
 *
 * This is a wrapper/adapter layer that delegates to underlying gateway packages
 * (laravel/cashier for Stripe, aiarmada/cashier-chip for CHIP).
 * No tables are created - subscriptions are stored in the respective package's tables.
 */
class Cashier
{
    /**
     * The customer model class.
     */
    public static string $customerModel = 'App\\Models\\User';

    /**
     * Indicates if past due subscriptions should be considered inactive.
     */
    public static bool $deactivatePastDue = true;

    /**
     * Indicates if incomplete subscriptions should be considered inactive.
     */
    public static bool $deactivateIncomplete = true;

    /**
     * Indicates if Cashier routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * The custom currency formatter.
     */
    protected static ?Closure $formatCurrencyUsing = null;

    /**
     * Get the GatewayManager instance.
     */
    public static function gateway(?string $gateway = null): GatewayContract
    {
        return app(GatewayManager::class)->gateway($gateway);
    }

    /**
     * Get the GatewayManager instance.
     */
    public static function manager(): GatewayManager
    {
        return app(GatewayManager::class);
    }

    /**
     * Set the customer model class.
     */
    public static function useCustomerModel(string $model): void
    {
        static::$customerModel = $model;
    }

    /**
     * Set whether past due subscriptions should be considered inactive.
     */
    public static function deactivatePastDue(bool $deactivate = true): void
    {
        static::$deactivatePastDue = $deactivate;

        // Sync to underlying packages if available
        // @codeCoverageIgnoreStart
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            \Laravel\Cashier\Cashier::$deactivatePastDue = $deactivate;
        }
        if (class_exists(\AIArmada\CashierChip\Cashier::class)) {
            \AIArmada\CashierChip\Cashier::$deactivatePastDue = $deactivate;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Set whether incomplete subscriptions should be considered inactive.
     */
    public static function deactivateIncomplete(bool $deactivate = true): void
    {
        static::$deactivateIncomplete = $deactivate;

        // Sync to underlying packages if available
        // @codeCoverageIgnoreStart
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            \Laravel\Cashier\Cashier::$deactivateIncomplete = $deactivate;
        }
        if (class_exists(\AIArmada\CashierChip\Cashier::class)) {
            \AIArmada\CashierChip\Cashier::$deactivateIncomplete = $deactivate;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Set a custom currency formatter.
     */
    public static function formatCurrencyUsing(Closure $callback): void
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     */
    public static function formatAmount(int $amount, ?string $currency = null, ?string $locale = null): string
    {
        if (static::$formatCurrencyUsing) {
            return (static::$formatCurrencyUsing)($amount, $currency, $locale);
        }

        $currency = mb_strtoupper($currency ?? config('cashier.currency', 'USD'));
        $locale = $locale ?? config('cashier.locale', config('app.locale', 'en'));

        return Money::$currency($amount, true)->format($locale);
    }

    /**
     * Configure Cashier to not register its routes.
     */
    public static function ignoreRoutes(): void
    {
        static::$registersRoutes = false;
    }

    /**
     * Get the default currency.
     */
    public static function defaultCurrency(): string
    {
        return config('cashier.currency', 'USD');
    }

    /**
     * Get the default gateway name.
     */
    public static function defaultGateway(): string
    {
        return config('cashier.default', 'stripe');
    }

    /**
     * Get available gateway names.
     *
     * @return array<string>
     */
    public static function availableGateways(): array
    {
        return array_keys(config('cashier.gateways', []));
    }

    /**
     * Get supported gateways (alias for facade compatibility).
     *
     * @return array<string>
     */
    public static function supportedGateways(): array
    {
        return static::availableGateways();
    }
}
