<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Enums\EmptyCartBehavior;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\UnknownModelException;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Support\CartLimits;
use AIArmada\Cart\Support\CartMoney;

trait ManagesItems
{
    /**
     * Add item(s) to the cart
     *
     * @param  string|int|array<array<string, mixed>>  $id
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|object|null  $conditions
     */
    public function add(
        string | int | array $id,
        ?string $name = null,
        float | int | string | null $price = null,
        mixed $quantity = 1,
        array $attributes = [],
        array | object | null $conditions = null,
        string | object | null $associatedModel = null
    ): CartItem | CartCollection {
        // Handle array input - distinguish between single item and multiple items
        if (is_array($id)) {
            // If array has 'id' key, it's a single item array
            // Otherwise, it's an array of items
            if (isset($id['id'])) {
                // Single item array: ['id' => '...', 'name' => '...', ...]
                /** @var string|null $name */
                $name = isset($id['name']) && is_string($id['name']) ? $id['name'] : null;
                /** @var float|int|string|null $price */
                $price = $id['price'] ?? null;
                /** @var int $quantity */
                $quantity = $this->resolveIntQuantity($id['quantity'] ?? 1, 'add quantity');
                /** @var array<string, mixed> $attributes */
                $attributes = isset($id['attributes']) && is_array($id['attributes']) ? $id['attributes'] : [];
                /** @var array<string, mixed>|object|null $conditions */
                $conditions = $id['conditions'] ?? null;
                /** @var object|string|null $associatedModel */
                $associatedModel = $id['associated_model'] ?? null;

                /** @var string|int $itemId */
                $itemId = $id['id'];

                return $this->addItemInternal(
                    $itemId,
                    $name,
                    $price,
                    $quantity,
                    $attributes,
                    $conditions,
                    $associatedModel
                );
            }

            // Multiple items: [['id' => '...'], ['id' => '...']]
            return $this->addMultiple($id);
        }

        return $this->addItemInternal(
            $id,
            $name,
            $price,
            $this->resolveIntQuantity($quantity, 'add quantity'),
            $attributes,
            $conditions,
            $associatedModel
        );
    }

    /**
     * Update cart item
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string | int $id, array $data): ?CartItem
    {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        $cartItems = $this->getItems();

        if (! $cartItems->has($id)) {
            return null;
        }

        $item = $cartItems->get($id);
        assert($item !== null, 'Item should exist since we checked has()');

        // Handle quantity updates
        if (isset($data['quantity'])) {
            $quantity = $data['quantity'];

            if (is_array($quantity)) {
                // Absolute quantity update; a missing value defaults to 0 (removal).
                $newQuantity = array_key_exists('value', $quantity)
                    ? $this->resolveIntQuantity($quantity['value'], 'update quantity')
                    : 0;
            } else {
                // Relative quantity update (default behavior)
                $newQuantity = $item->quantity + $this->resolveIntQuantity($quantity, 'update quantity');
            }

            // Check for removal BEFORE creating new CartItem to avoid exceptions
            if ($newQuantity <= 0) {
                return $this->remove($id);
            }

            $item = $item->setQuantity($newQuantity);
        }

        // Update other properties
        foreach (['name', 'price', 'attributes'] as $property) {
            if (isset($data[$property])) {
                $method = 'set' . ucfirst($property);
                $value = $property === 'price' ? $this->normalizePrice($data[$property]) : $data[$property];
                $item = $item->$method($value);
            }
        }

        // Update cart
        $cartItems->put($id, $item);
        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        // Mark dynamic conditions dirty before dispatching events so
        // listeners observe the latest cart state.
        $this->markDynamicConditionsDirty();

        $this->dispatchEvent(new ItemUpdated($item, $this));

        return $item;
    }

    /**
     * Remove item from cart
     */
    public function remove(string | int $id): ?CartItem
    {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        $cartItems = $this->getItems();

        if (! $cartItems->has($id)) {
            return null;
        }

        $item = $cartItems->get($id);
        assert($item !== null, 'Item should exist since we checked has()');
        $cartItems->forget($id);
        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        // Mark dynamic conditions dirty before dispatching events so
        // listeners observe the latest cart state.
        $this->markDynamicConditionsDirty();

        $this->dispatchEvent(new ItemRemoved($item, $this));

        // Handle empty cart based on configured behavior
        if ($cartItems->isEmpty()) {
            $this->handleEmptyCart();
        }

        return $item;
    }

    /**
     * Get cart item by ID
     */
    public function get(string | int $id): ?CartItem
    {
        // Ensure dynamic conditions are evaluated before returning item
        // This is necessary for item-level dynamic conditions to be applied
        $this->evaluateDynamicConditionsIfDirty();

        // Normalize ID to string for consistent handling
        $id = (string) $id;

        return $this->getItems()->get($id);
    }

    /**
     * Check if cart has item with given ID
     */
    public function has(string | int $id): bool
    {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        return $this->getItems()->has($id);
    }

    /**
     * Search cart content with callback
     */
    public function search(callable $callback): CartCollection
    {
        // Ensure dynamic conditions are evaluated before searching
        $this->evaluateDynamicConditionsIfDirty();

        return $this->getItems()->filter($callback);
    }

    /**
     * Internal method to add a single item (bypasses rate limit check for recursive calls).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|object|null  $conditions
     */
    private function addItemInternal(
        string | int $id,
        ?string $name,
        float | int | string | null $price,
        int $quantity,
        array $attributes,
        array | object | null $conditions,
        string | object | null $associatedModel
    ): CartItem {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        $cartItems = $this->getItems();

        $limits = CartLimits::fromConfig();
        if (! $cartItems->has((string) $id) && $cartItems->count() >= $limits->maxItems) {
            throw new InvalidCartItemException("Cart cannot contain more than {$limits->maxItems} items");
        }

        // Create cart item
        $item = $this->createCartItem([
            'id' => $id,
            'name' => $name,
            'price' => $this->normalizePrice($price),
            'quantity' => $quantity,
            'attributes' => $attributes,
            'conditions' => $conditions,
            'associated_model' => $associatedModel,
        ]);

        // Check if item already exists in cart
        $isFirstItem = $cartItems->isEmpty();

        if ($cartItems->has($id)) {
            // Update existing item quantity
            $existingItem = $cartItems->get($id);
            assert($existingItem !== null, 'Item should exist since we checked has()');
            $item = $item->setQuantity($existingItem->quantity + $quantity);
        }

        // Store in cart
        $cartItems->put($id, $item);
        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        // Mark dynamic conditions dirty before dispatching events so
        // listeners observe the latest cart state.
        $this->markDynamicConditionsDirty();

        // Dispatch CartCreated event only when adding the first item to an empty cart
        if ($isFirstItem) {
            $this->dispatchEvent(new CartCreated($this));
        }

        // Dispatch ItemAdded event
        $this->dispatchEvent(new ItemAdded($item, $this));

        return $item;
    }

    /**
     * Invalidate pipeline cache if the trait is available.
     */
    private function invalidatePipelineCacheIfEnabled(): void
    {
        $this->invalidatePipelineCache();
    }

    /**
     * Handle cart when it becomes empty based on configured behavior.
     */
    private function handleEmptyCart(): void
    {
        $behavior = EmptyCartBehavior::tryFrom(
            config('cart.empty_cart_behavior', 'destroy')
        ) ?? EmptyCartBehavior::Destroy;

        match ($behavior) {
            EmptyCartBehavior::Destroy => $this->destroy(),
            EmptyCartBehavior::Clear => $this->clear(),
            EmptyCartBehavior::Preserve => null, // Do nothing, keep conditions and metadata
        };
    }

    /**
     * Add multiple items to cart in one batch: every row is validated before
     * anything is written (all-or-nothing), then the cart is loaded once,
     * updated in memory, and saved once with a single sync downstream.
     *
     * @param  array<array<string, mixed>>  $items
     */
    private function addMultiple(array $items): CartCollection
    {
        if ($items === []) {
            return new CartCollection;
        }

        $rows = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidCartItemException("Cart import row [{$index}] must be an array.");
            }

            $rows[] = $this->normalizeImportRow($item, $index);
        }

        $cartItems = $this->getItems();
        $isFirstItem = $cartItems->isEmpty();

        $limits = CartLimits::fromConfig();
        $seenNew = [];
        $newDistinct = 0;

        foreach ($rows as $row) {
            if (! $cartItems->has($row['id']) && ! isset($seenNew[$row['id']])) {
                $seenNew[$row['id']] = true;
                $newDistinct++;
            }
        }

        if ($cartItems->count() + $newDistinct > $limits->maxItems) {
            throw new InvalidCartItemException("Cart cannot contain more than {$limits->maxItems} items");
        }

        $added = new CartCollection;

        foreach ($rows as $row) {
            $item = $this->createCartItem([
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => $this->normalizePrice($row['price']),
                'quantity' => $row['quantity'],
                'attributes' => $row['attributes'],
                'conditions' => $row['conditions'],
                'associated_model' => $row['associated_model'],
            ]);

            if ($cartItems->has($row['id'])) {
                $existingItem = $cartItems->get($row['id']);
                assert($existingItem !== null, 'Item should exist since we checked has()');
                $item = $item->setQuantity($existingItem->quantity + $row['quantity']);
            }

            $cartItems->put($row['id'], $item);
            $added->put($item->id, $item);
        }

        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        // Mark dynamic conditions dirty before dispatching events so
        // listeners observe the latest cart state.
        $this->markDynamicConditionsDirty();

        // Dispatch CartCreated event only when adding the first item to an empty cart
        if ($isFirstItem && ! $added->isEmpty()) {
            $this->dispatchEvent(new CartCreated($this));
        }

        foreach ($added as $addedItem) {
            $this->dispatchEvent(new ItemAdded($addedItem, $this));
        }

        return $added;
    }

    /**
     * Validate one import row's id/quantity/attributes shape.
     *
     * @param  array<string, mixed>  $item
     * @return array{id: string, name: string|null, price: float|int|string|null, quantity: int, attributes: array<string, mixed>, conditions: array<string, mixed>|object|null, associated_model: string|object|null}
     */
    private function normalizeImportRow(array $item, int | string $index): array
    {
        $id = $item['id'] ?? null;

        if ((! is_string($id) && ! is_int($id)) || $id === '') {
            throw new InvalidCartItemException("Cart import row [{$index}] requires a valid id.");
        }

        $attributes = $item['attributes'] ?? [];

        if (! is_array($attributes)) {
            throw new InvalidCartItemException("Cart import row [{$index}] attributes must be an array.");
        }

        return [
            'id' => (string) $id,
            'name' => $item['name'] ?? null,
            'price' => $item['price'] ?? null,
            'quantity' => $this->resolveIntQuantity($item['quantity'] ?? 1, "import row [{$index}] quantity"),
            'attributes' => $attributes,
            'conditions' => $item['conditions'] ?? null,
            'associated_model' => $item['associated_model'] ?? null,
        ];
    }

    /**
     * Resolve a caller-supplied quantity to an integer.
     *
     * Integers pass through; validated integer numeric strings are cast. Floats,
     * booleans, arrays, and non-numeric strings are rejected with a domain
     * exception instead of failing later with a TypeError.
     */
    private function resolveIntQuantity(mixed $quantity, string $context): int
    {
        if (is_int($quantity)) {
            return $quantity;
        }

        if (is_string($quantity) && preg_match('/^-?\d+$/', mb_trim($quantity)) === 1) {
            return (int) $quantity;
        }

        throw new InvalidCartItemException("Cart item quantity for {$context} must be an integer.");
    }

    /**
     * Create a cart item from array data
     *
     * @param  array<string, mixed>  $data
     */
    private function createCartItem(array $data): CartItem
    {
        $this->validateCartItem($data);

        return new CartItem(
            id: $data['id'],
            name: $data['name'],
            price: $data['price'],
            quantity: $data['quantity'],
            attributes: $data['attributes'] ?? [],
            conditions: $data['conditions'] ?? [],
            associatedModel: $data['associated_model'] ?? null
        );
    }

    /**
     * Validate cart item data
     *
     * @param  array<string, mixed>  $data
     */
    private function validateCartItem(array $data): void
    {
        $limits = CartLimits::fromConfig();

        if (empty($data['id'])) {
            throw new InvalidCartItemException('Cart item ID is required');
        }

        if (empty($data['name'])) {
            throw new InvalidCartItemException('Cart item name is required');
        }

        if (mb_strlen((string) $data['id']) > $limits->maxStringLength || mb_strlen((string) $data['name']) > $limits->maxStringLength) {
            throw new InvalidCartItemException("Cart item ID and name cannot exceed {$limits->maxStringLength} characters");
        }

        if (! is_numeric($data['price']) || $data['price'] < 0) {
            throw new InvalidCartItemException('Cart item price must be a positive number');
        }

        if (! is_int($data['quantity']) || $data['quantity'] < 1) {
            throw new InvalidCartItemException('Cart item quantity must be a positive integer');
        }

        if ($data['quantity'] > $limits->maxItemQuantity) {
            throw new InvalidCartItemException("Cart item quantity cannot exceed {$limits->maxItemQuantity}");
        }

        // Validate associated model if provided
        if (isset($data['associated_model']) && is_string($data['associated_model'])) {
            if (! class_exists($data['associated_model'])) {
                throw new UnknownModelException("Model {$data['associated_model']} does not exist");
            }
        }
    }

    /**
     * Normalize a cart price boundary to integer minor units.
     *
     * Integer inputs are already minor units. Decimal float/string inputs are
     * major units and use explicit half-up rounding at this boundary.
     * Thousand separators (`,` or digit-grouping spaces, US-style) always imply
     * major units, so '1,000' and '1,000.00' both mean 100000 minor units.
     * Decimal-comma locales are not supported.
     */
    private function normalizePrice(float | int | string | null $price): int
    {
        if ($price === null || is_int($price)) {
            return $price ?? 0;
        }

        if (is_float($price)) {
            if (! is_finite($price)) {
                throw new InvalidCartItemException('Cart item price must be a finite number');
            }

            return CartMoney::minorFromDecimal((string) $price);
        }

        $normalized = mb_trim($price);
        $normalized = str_replace(['$', '€', '£', '¥', '₹', 'RM', '₱', '₩', '฿', '₫', '₪', '₨', 'kr', 'zł'], '', $normalized);

        if ($normalized === '') {
            return 0;
        }

        $hasThousandSeparator = str_contains($normalized, ',')
            || preg_match('/\d \d/', $normalized) === 1;
        $normalized = str_replace([',', ' '], '', $normalized);

        if ($normalized === '') {
            return 0;
        }

        if (! is_numeric($normalized) || ! is_finite((float) $normalized)) {
            throw new InvalidCartItemException('Cart item price must be a finite number');
        }

        return str_contains($normalized, '.') || $hasThousandSeparator
            ? CartMoney::minorFromDecimal($normalized)
            : (int) $normalized;
    }
}
