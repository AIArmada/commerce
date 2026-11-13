<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Traits\HasInventory;
use AIArmada\Orders\Models\OrderItem;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Reads the frozen composition snapshot stored on cart and order lines.
 *
 * A line containing the `components` key is a composite sale line. Its
 * parent is commercial identity only; inventory work always targets the
 * component models listed in the snapshot.
 */
final class CompositeComponents
{
    public const string ATTRIBUTE = 'components';

    /**
     * Return the component snapshot for a cart line, or null when the line
     * is not composite.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function fromCartItem(CartItem $item): ?array
    {
        if (! $item->attributes->has(self::ATTRIBUTE)) {
            return null;
        }

        return self::normalize($item->attributes->get(self::ATTRIBUTE));
    }

    /**
     * Return the component snapshot for an order line, or null when the line
     * is not composite.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function fromOrderItem(OrderItem $item): ?array
    {
        $metadata = $item->metadata;

        if (! is_array($metadata) || ! array_key_exists(self::ATTRIBUTE, $metadata)) {
            return null;
        }

        return self::normalize($metadata[self::ATTRIBUTE]);
    }

    /**
     * Return the component snapshot stored on an order, or null when absent.
     *
     * @param  array<string, mixed>  $metadata
     * @return list<array<string, mixed>>|null
     */
    public static function fromMetadata(array $metadata): ?array
    {
        if (! array_key_exists(self::ATTRIBUTE, $metadata)) {
            return null;
        }

        return self::normalize($metadata[self::ATTRIBUTE]);
    }

    /**
     * Normalize a component snapshot into stable scalar values.
     *
     * Invalid entries and non-positive per-package quantities are discarded;
     * the parent is still recognised as composite because the key exists.
     *
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            if (isset($value['quantity'])) {
                $value = [$value];
            } else {
                $flattened = [];

                foreach ($value as $entries) {
                    if (! is_array($entries)) {
                        continue;
                    }

                    if (array_is_list($entries)) {
                        $flattened = [...$flattened, ...$entries];

                        continue;
                    }

                    $flattened[] = $entries;
                }

                $value = $flattened;
            }
        }

        $components = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $quantity = $entry['quantity'] ?? null;

            if (! is_numeric($quantity) || (int) $quantity <= 0) {
                continue;
            }

            $components[] = [
                'product_id' => self::stringOrNull($entry['product_id'] ?? null),
                'variant_id' => self::stringOrNull($entry['variant_id'] ?? null),
                'inventoryable_type' => self::stringOrNull($entry['inventoryable_type'] ?? null),
                'inventoryable_id' => self::stringOrNull($entry['inventoryable_id'] ?? null),
                'quantity' => (int) $quantity,
                'tracks_inventory' => array_key_exists('tracks_inventory', $entry)
                    ? (bool) $entry['tracks_inventory']
                    : null,
                'requires_shipping' => array_key_exists('requires_shipping', $entry)
                    ? (bool) $entry['requires_shipping']
                    : null,
                'name' => self::stringOrNull($entry['name'] ?? null),
                'sku' => self::stringOrNull($entry['sku'] ?? null),
                'weight' => $entry['weight'] ?? null,
                'dimensions' => is_array($entry['dimensions'] ?? null) ? $entry['dimensions'] : null,
            ];
        }

        return $components;
    }

    /**
     * Resolve the actual inventory model for a component snapshot entry.
     *
     * Resolution only accepts explicitly inventoryable model classes and
     * always goes through that model's owner scope. A missing or foreign
     * model resolves to null instead of falling back to the parent.
     *
     * @param  array<string, mixed>  $component
     */
    public static function resolveInventoryModel(array $component): ?Model
    {
        $type = self::stringOrNull($component['inventoryable_type'] ?? null);
        $id = self::stringOrNull($component['inventoryable_id'] ?? null);

        if (($type === null) !== ($id === null)) {
            return null;
        }

        if ($type !== null && $id !== null) {
            $class = Relation::getMorphedModel($type) ?? $type;

            if (! is_string($class) || ! self::isAllowedInventoryableClass($class)) {
                return null;
            }

            return self::findOwned($class, $id);
        }

        $variantId = self::stringOrNull($component['variant_id'] ?? null);

        if ($variantId !== null) {
            $variantClass = self::configuredClass('inventory.models.variant', 'AIArmada\\Products\\Models\\Variant');

            if ($variantClass === null) {
                return null;
            }

            return self::findOwned($variantClass, $variantId);
        }

        $productId = self::stringOrNull($component['product_id'] ?? null);

        if ($productId !== null) {
            $productClass = self::configuredClass('inventory.models.product', 'AIArmada\\Products\\Models\\Product');

            if ($productClass === null) {
                return null;
            }

            return self::findOwned($productClass, $productId);
        }

        return null;
    }

    /**
     * Check whether a model is a composite parent that owns no stock.
     */
    public static function isCompositeModel(Model $model): bool
    {
        if (method_exists($model, 'isComposite') && $model->isComposite()) {
            return true;
        }

        $type = $model->getAttribute('type');

        if ($type instanceof BackedEnum) {
            $type = $type->value;
        }

        if (is_string($type) && mb_strtolower($type) === 'composite') {
            return true;
        }

        return (bool) $model->getAttribute('is_composite');
    }

    public static function tracksInventory(Model $model): bool
    {
        if (method_exists($model, 'tracksInventory')) {
            return (bool) $model->tracksInventory();
        }

        return true;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function findOwned(string $class, string $id): ?Model
    {
        /** @var Model $probe */
        $probe = new $class;

        /** @var Builder<Model> $query */
        $query = $class::query();

        self::applyOwnerScope($query, $probe);

        return $query->whereKey($id)->first();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function applyOwnerScope(Builder $query, Model $model): void
    {
        if (! method_exists($model, 'ownerScopeConfig')) {
            return;
        }

        /** @var mixed $config */
        $config = $model->ownerScopeConfig();

        if (! $config instanceof OwnerScopeConfig || ! $config->enabled) {
            return;
        }

        $owner = OwnerContext::resolve();

        // Leave the model's existing global scope in place when there is no
        // explicit context; it will raise the canonical owner error instead of
        // silently running global work.
        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            return;
        }

        $query->withoutGlobalScope(OwnerScope::class);

        OwnerQuery::applyToEloquentBuilder(
            $query,
            $owner,
            $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function isAllowedInventoryableClass(string $class): bool
    {
        if (! class_exists($class) || ! is_a($class, Model::class, true)) {
            return false;
        }

        $candidates = array_filter([
            config('inventory.models.variant'),
            config('inventory.models.product'),
            class_exists('AIArmada\\Products\\Models\\Variant') ? 'AIArmada\\Products\\Models\\Variant' : null,
            class_exists('AIArmada\\Products\\Models\\Product') ? 'AIArmada\\Products\\Models\\Product' : null,
        ], static fn (mixed $candidate): bool => is_string($candidate) && class_exists($candidate));

        foreach ($candidates as $candidate) {
            if ($class === $candidate || is_a($class, $candidate, true)) {
                return true;
            }
        }

        if (is_a($class, InventoryableInterface::class, true)) {
            return true;
        }

        if (interface_exists('AIArmada\\Products\\Contracts\\Inventoryable')
            && is_a($class, 'AIArmada\\Products\\Contracts\\Inventoryable', true)) {
            return true;
        }

        return in_array(HasInventory::class, class_uses_recursive($class), true);
    }

    /**
     * @return class-string<Model>|null
     */
    private static function configuredClass(string $key, string $fallback): ?string
    {
        $configured = config($key);

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            /** @var class-string<Model> $configured */
            return $configured;
        }

        if (class_exists($fallback)) {
            /** @var class-string<Model> $fallback */
            return $fallback;
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
