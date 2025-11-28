<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use Akaunting\Money\Money;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Models\Subscription;
use AIArmada\Cashier\Models\SubscriptionItem;

/**
 * Main Cashier class for multi-gateway payment management.
 */
class Cashier
{
    /**
     * The customer model class.
     */
    public static string $customerModel = 'App\\Models\\User';

    /**
     * The Subscription model class.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The SubscriptionItem model class.
     */
    public static string $subscriptionItemModel = SubscriptionItem::class;

    /**
     * Indicates if past due subscriptions should be considered inactive.
     */
    public static bool $deactivatePastDue = true;

    /**
     * Indicates if incomplete subscriptions should be considered inactive.
     */
    public static bool $deactivateIncomplete = true;

    /**
     * The custom currency formatter.
     */
    protected static ?\Closure $formatCurrencyUsing = null;

    /**
     * Indicates if Cashier routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if Cashier migrations will be run.
     */
    public static bool $runsMigrations = true;

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
     * Set the Subscription model class.
     */
    public static function useSubscriptionModel(string $model): void
    {
        static::$subscriptionModel = $model;
    }

    /**
     * Set the SubscriptionItem model class.
     */
    public static function useSubscriptionItemModel(string $model): void
    {
        static::$subscriptionItemModel = $model;
    }

    /**
     * Set whether past due subscriptions should be considered inactive.
     */
    public static function deactivatePastDue(bool $deactivate = true): void
    {
        static::$deactivatePastDue = $deactivate;
    }

    /**
     * Set whether incomplete subscriptions should be considered inactive.
     */
    public static function deactivateIncomplete(bool $deactivate = true): void
    {
        static::$deactivateIncomplete = $deactivate;
    }

    /**
     * Set a custom currency formatter.
     */
    public static function formatCurrencyUsing(\Closure $callback): void
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

        $currency = strtoupper($currency ?? config('cashier.currency', 'USD'));
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
     * Configure Cashier to not run its migrations.
     */
    public static function ignoreMigrations(): void
    {
        static::$runsMigrations = false;
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
}
