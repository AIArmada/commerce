<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support\Integration;

use AIArmada\Cart\Cart;
use AIArmada\Customers\Models\Customer;
use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Products\Models\Product;

final class TicketingIntegration
{
    public static function cartAvailable(): bool
    {
        return class_exists(Cart::class);
    }

    public static function inventoryAvailable(): bool
    {
        return interface_exists(InventoryableInterface::class);
    }

    public static function productsAvailable(): bool
    {
        return class_exists(Product::class);
    }

    public static function ordersAvailable(): bool
    {
        return class_exists(Order::class);
    }

    public static function customersAvailable(): bool
    {
        return class_exists(Customer::class);
    }
}
