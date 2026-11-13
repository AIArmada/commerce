<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds one aggregated demand row per actual inventory model.
 *
 * Composite lines expand to their component quantities multiplied by the
 * number of packages. Standalone lines for the same model merge into the
 * same row so allocation and deduction happen exactly once for that stock
 * identity.
 */
final class InventoryDemandResolver
{
    /**
     * @param  iterable<CartItem>  $items
     * @return array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>
     */
    public static function forCart(iterable $items): array
    {
        $demand = [];

        foreach ($items as $item) {
            if ($item instanceof CartItem) {
                self::addCartItem($demand, $item);
            }
        }

        return self::sorted($demand);
    }

    /**
     * @param  iterable<OrderItem>  $items
     * @param  array<string, mixed>  $orderMetadata
     * @return array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>
     */
    public static function forOrder(iterable $items, array $orderMetadata = []): array
    {
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);
        $demand = [];
        $itemComponents = [];

        foreach ($items as $item) {
            if (! $item instanceof OrderItem) {
                continue;
            }

            $components = CompositeComponents::fromOrderItem($item);

            if ($components !== null) {
                $itemComponents[(string) $item->getKey()] = $components;
            }
        }

        if ($itemComponents !== []) {
            foreach ($items as $item) {
                if (! $item instanceof OrderItem) {
                    continue;
                }

                $key = (string) $item->getKey();

                if (array_key_exists($key, $itemComponents)) {
                    self::addComponents(
                        $demand,
                        $itemComponents[$key],
                        (int) $item->quantity,
                        $key,
                        $item->name,
                    );

                    continue;
                }

                self::addOrdinaryOrderItem($demand, $item);
            }

            return self::sorted($demand);
        }

        $orderComponents = CompositeComponents::fromMetadata($orderMetadata);

        if ($orderComponents !== null) {
            self::addComponents($demand, $orderComponents, 1, 'order', 'order');

            return self::sorted($demand);
        }

        foreach ($items as $item) {
            if ($item instanceof OrderItem) {
                self::addOrdinaryOrderItem($demand, $item);
            }
        }

        return self::sorted($demand);
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private static function addCartItem(array &$demand, CartItem $item): void
    {
        $components = CompositeComponents::fromCartItem($item);

        if ($components !== null) {
            self::addComponents($demand, $components, (int) $item->quantity, (string) $item->id, $item->name);

            return;
        }

        $model = $item->getAssociatedModel();

        if (! $model instanceof Model || CompositeComponents::isCompositeModel($model)) {
            return;
        }

        self::addDemand($demand, $model, (int) $item->quantity, (string) $item->id, $item->name);
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private static function addOrdinaryOrderItem(array &$demand, OrderItem $item): void
    {
        $model = $item->purchasable;

        if (! $model instanceof Model || CompositeComponents::isCompositeModel($model)) {
            return;
        }

        if (! CompositeComponents::tracksInventory($model)) {
            return;
        }

        self::addDemand(
            $demand,
            $model,
            (int) $item->quantity,
            (string) $item->getKey(),
            $item->name,
        );
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     * @param  list<array<string, mixed>>  $components
     */
    private static function addComponents(
        array &$demand,
        array $components,
        int $packageQuantity,
        string $sourceId,
        string $sourceName,
    ): void {
        if ($packageQuantity <= 0) {
            return;
        }

        foreach ($components as $component) {
            $tracksInventory = $component['tracks_inventory'] ?? null;

            if ($tracksInventory === false) {
                continue;
            }

            $model = CompositeComponents::resolveInventoryModel($component);

            if (! $model instanceof Model) {
                continue;
            }

            if ($tracksInventory === null && ! CompositeComponents::tracksInventory($model)) {
                continue;
            }

            $quantity = (int) $component['quantity'] * $packageQuantity;

            self::addDemand($demand, $model, $quantity, $sourceId, $sourceName);
        }
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private static function addDemand(
        array &$demand,
        Model $model,
        int $quantity,
        string $sourceId,
        string $sourceName,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $key = $model->getMorphClass() . ':' . $model->getKey();

        if (! isset($demand[$key])) {
            $demand[$key] = [
                'model' => $model,
                'quantity' => 0,
                'sources' => [],
            ];
        }

        $demand[$key]['quantity'] += $quantity;

        $source = $demand[$key]['sources'][$sourceId] ?? [
            'name' => $sourceName,
            'quantity' => 0,
        ];
        $source['quantity'] += $quantity;
        $demand[$key]['sources'][$sourceId] = $source;
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     * @return array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>
     */
    private static function sorted(array $demand): array
    {
        ksort($demand);

        foreach ($demand as &$entry) {
            ksort($entry['sources']);
        }

        unset($entry);

        return $demand;
    }
}
