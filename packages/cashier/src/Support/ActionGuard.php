<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Exceptions\Gateway\InvalidGatewayException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;

/**
 * Shared input validation and owner authorization for Cashier actions.
 *
 * Gateway-name checks are config-based on purpose: actions resolve gateways
 * through the Cashier facade, so validation must not depend on the same
 * container binding it guards.
 */
final class ActionGuard
{
    /**
     * Resolve and validate a gateway name.
     *
     * @throws InvalidGatewayException
     */
    public static function gatewayName(?string $gateway): string
    {
        $name = $gateway ?? (string) config('cashier.default', 'stripe');

        if ($name === '' || ! self::isSupported($name)) {
            throw InvalidGatewayException::create($name);
        }

        return $name;
    }

    /**
     * Assert a positive money amount in minor units.
     *
     * @throws InvalidArgumentException
     */
    public static function positiveAmount(int $amount, string $field = 'amount'): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Cashier {$field} must be a positive integer in minor units.");
        }
    }

    /**
     * Assert a non-empty string argument.
     *
     * @throws InvalidArgumentException
     */
    public static function nonEmptyString(?string $value, string $field): void
    {
        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException("Cashier {$field} must be a non-empty string.");
        }
    }

    /**
     * Assert the billable belongs to the currently resolved owner.
     *
     * No-op when owner mode is disabled or when the billable carries no
     * owner tuple to enforce. Fails closed otherwise.
     *
     * @throws AuthorizationException
     */
    public static function assertBillableInScope(BillableContract $billable): void
    {
        if (! (bool) config('commerce-support.owner.enabled', false)) {
            return;
        }

        if (! $billable instanceof Model || ! method_exists($billable, 'belongsToOwner')) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null || ! $billable->belongsToOwner($owner)) {
            throw new AuthorizationException('Cross-owner billing mutation blocked for ' . $billable::class . '.');
        }
    }

    /**
     * Assert a subscription's owner belongs to the currently resolved owner.
     *
     * @throws AuthorizationException
     */
    public static function assertSubscriptionInScope(SubscriptionContract $subscription): void
    {
        if (! (bool) config('commerce-support.owner.enabled', false)) {
            return;
        }

        self::assertBillableInScope($subscription->owner());
    }

    /**
     * Mirror of GatewayManager::supportedGateways() without container use.
     */
    private static function isSupported(string $name): bool
    {
        $configured = array_keys((array) config('cashier.gateways', []));

        if (! in_array($name, $configured, true)) {
            return false;
        }

        return match ($name) {
            'stripe' => class_exists(Cashier::class),
            'chip' => class_exists(\AIArmada\CashierChip\Billing\Cashier::class)
                && class_exists(ChipCollectService::class),
            default => true,
        };
    }
}
