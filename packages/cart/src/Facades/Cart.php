<?php

declare(strict_types=1);

namespace AIArmada\Cart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Cart system.
 *
 * @method static \AIArmada\Cart\CartManager setInstance(string $name)
 * @method static \AIArmada\Cart\CartManager setIdentifier(string $identifier)
 * @method static \AIArmada\Cart\CartManager forgetIdentifier()
 * @method static string getIdentifier()
 * @method static \AIArmada\Cart\Cart|null getById(string $uuid)
 * @method static bool exists(?string $identifier = null, ?string $instance = null)
 * @method static void destroy(?string $identifier = null, ?string $instance = null)
 * @method static array<string> instances(?string $identifier = null)
 * @method static string instance()
 * @method static \AIArmada\Cart\Storage\StorageInterface storage()
 * @method static \AIArmada\Cart\Cart getCartInstance(string $name, ?string $identifier = null)
 * @method static \AIArmada\Cart\Storage\StorageInterface session(?string $sessionKey = null)
 * @method static self formatted()
 * @method static self raw()
 * @method static self currency(?string $currency = null)
 * @method static \AIArmada\Cart\Models\CartItem|\AIArmada\Cart\Collections\CartCollection add(string|array<string, mixed> $id, ?string $name = null, float|string|null $price = null, int $quantity = 1, array<string, mixed> $attributes = [], array<string, mixed>|\AIArmada\Cart\Conditions\CartCondition|null $conditions = null, string|object|null $associatedModel = null)
 * @method static \AIArmada\Cart\Models\CartItem|null update(string $id, array<string, mixed> $data)
 * @method static \AIArmada\Cart\Models\CartItem|null remove(string $id)
 * @method static \AIArmada\Cart\Models\CartItem|null get(string $id)
 * @method static \AIArmada\Cart\Collections\CartCollection getItems()
 * @method static array<string, mixed> getContent()
 * @method static bool isEmpty()
 * @method static int getTotalQuantity()
 * @method static \Akaunting\Money\Money subtotal()
 * @method static \Akaunting\Money\Money subtotalWithoutConditions()
 * @method static \Akaunting\Money\Money total()
 * @method static \Akaunting\Money\Money totalWithoutConditions()
 * @method static \Akaunting\Money\Money savings()
 * @method static float getRawSubtotal()
 * @method static float getRawTotal()
 * @method static float getRawSubtotalWithoutConditions()
 * @method static bool clear()
 * @method static \AIArmada\Cart\Cart addCondition(\AIArmada\Cart\Conditions\CartCondition|array<string, mixed> $condition)
 * @method static \AIArmada\Cart\Collections\CartConditionCollection getConditions()
 * @method static \AIArmada\Cart\Conditions\CartCondition|null getCondition(string $name)
 * @method static bool removeCondition(string $name)
 * @method static bool clearConditions()
 * @method static \AIArmada\Cart\Collections\CartConditionCollection getConditionsByType(string $type)
 * @method static bool removeConditionsByType(string $type)
 * @method static bool addItemCondition(string $itemId, \AIArmada\Cart\Conditions\CartCondition $condition)
 * @method static bool removeItemCondition(string $itemId, string $conditionName)
 * @method static bool clearItemConditions(string $itemId)
 * @method static self addDiscount(string $name, string $value, string $target = 'subtotal')
 * @method static self addTax(string $name, string $value, string $target = 'subtotal')
 * @method static self addFee(string $name, string $value, string $target = 'total')
 * @method static self addShipping(string $name, string|float $value, string $method = 'standard', array<string, mixed> $attributes = [])
 * @method static void removeShipping()
 * @method static \AIArmada\Cart\Conditions\CartCondition|null getShipping()
 * @method static string|null getShippingMethod()
 * @method static float|null getShippingValue()
 * @method static int count()
 * @method static int countItems()
 * @method static array<string, mixed> toArray()
 * @method static self setMetadata(string $key, mixed $value)
 * @method static mixed getMetadata(string $key, mixed $default = null)
 * @method static bool hasMetadata(string $key)
 * @method static self removeMetadata(string $key)
 * @method static self setMetadataBatch(array<string, mixed> $metadata)
 * @method static bool swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default')
 * @method static int|null getVersion()
 * @method static string|null getId()
 *
 * @see \AIArmada\Cart\CartManager
 */
final class Cart extends Facade
{
    /**
     * Handle dynamic, static calls to the facade.
     *
     * @param  array<string, mixed>  $args
     */
    public static function __callStatic($method, $args): mixed
    {
        $instance = self::getFacadeRoot();

        return $instance->$method(...$args);
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cart';
    }
}
