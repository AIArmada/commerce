<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Facades;

use AIArmada\CashierChip\Billing\Cashier;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void useCustomerModel(string $customerModel)
 * @method static void useSubscriptionModel(string $subscriptionModel)
 * @method static void useSubscriptionItemModel(string $subscriptionItemModel)
 */
final class CashierChip extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Cashier::class;
    }
}
