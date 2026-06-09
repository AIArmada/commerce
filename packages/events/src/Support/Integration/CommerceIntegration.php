<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Integration;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Processing;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class CommerceIntegration
{
    public static function aiArmadaOrderFulfillmentAvailable(): bool
    {
        return class_exists(Customer::class)
            && class_exists(Order::class)
            && class_exists(OrderItem::class)
            && interface_exists(OrderServiceInterface::class)
            && class_exists(Processing::class);
    }

    public static function aiArmadaCheckoutAvailable(): bool
    {
        return interface_exists(CartManagerInterface::class)
            && interface_exists(CheckoutServiceInterface::class)
            && class_exists(Product::class)
            && class_exists(Variant::class);
    }

    /**
     * @return class-string<Model>|null
     */
    public static function modelClass(string $integrationKey): ?string
    {
        $model = config("events.integrations.{$integrationKey}");

        if (! is_string($model) || $model === '') {
            return null;
        }

        if (! is_a($model, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $model */
        return $model;
    }

    /**
     * @return class-string<Model>
     */
    public static function requireModelClass(string $integrationKey, string $feature): string
    {
        $model = self::modelClass($integrationKey);

        if ($model !== null) {
            return $model;
        }

        throw new RuntimeException(sprintf(
            'The events %s integration is unavailable. Install the matching AIArmada package or configure events.integrations.%s.',
            $feature,
            $integrationKey,
        ));
    }
}
