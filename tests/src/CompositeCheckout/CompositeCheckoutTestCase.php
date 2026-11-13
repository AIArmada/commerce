<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CompositeCheckout;

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Inventory\InventoryServiceProvider;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Pricing\PricingServiceProvider;
use AIArmada\Promotions\PromotionsServiceProvider;
use Lorisleiva\Actions\ActionServiceProvider;

/**
 * Boots the real pricing, orders, and inventory providers so a checkout test
 * can drive the same services production uses instead of faking the order and
 * stock seams.
 *
 * `ActionServiceProvider` is required before the orders provider: it turns the
 * `AsAction` outbox classes into resolvable console commands, which the base
 * test case does not otherwise provide.
 */
abstract class CompositeCheckoutTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        $providers[] = ActionServiceProvider::class;
        $providers[] = PricingServiceProvider::class;
        $providers[] = PromotionsServiceProvider::class;
        $providers[] = OrdersServiceProvider::class;
        $providers[] = InventoryServiceProvider::class;

        return $providers;
    }
}
