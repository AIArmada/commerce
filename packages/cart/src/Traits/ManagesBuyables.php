<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Contracts\BuyablePriceResolver;
use AIArmada\Cart\Contracts\ContextualBuyableValidatorInterface;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\ProductNotPurchasableException;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Support\BuyablePriceData;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\CommerceSupport\Support\BuyableLineData;
use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\CommerceSupport\Support\PurchaseContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Adds canonical catalog buyables to the cart with accumulated validation.
 *
 * Cart itself never depends on a catalog package. A buyable supplies its own
 * identity, currency, quantity rules, stock, and normalized shipping/tax
 * attributes. An optional {@see BuyablePriceResolver} supplies the same
 * pricing policy used by checkout.
 */
trait ManagesBuyables
{
    /**
     * Add a canonical buyable line.
     *
     * @param  array<string, mixed>  $extraAttributes
     * @param  string|null  $lineIdentifier  Distinct line identity, for example required merchandise attached to a ticket
     *
     * @throws ProductNotPurchasableException When the accumulated quantity is invalid
     */
    public function addBuyable(
        Buyable $buyable,
        int $quantity = 1,
        array $extraAttributes = [],
        ?string $lineIdentifier = null,
        ?PurchaseContext $purchaseContext = null,
    ): CartItem {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least one.');
        }

        $line = BuyableLineData::fromBuyable($buyable, $quantity, $this->withoutCanonicalCompositeAttributes($extraAttributes));
        $lineId = $this->resolveBuyableLineIdentifier($buyable, $lineIdentifier, $purchaseContext);

        $addedItem = null;
        $isFirstItem = false;

        $this->mutateItems(function (CartCollection $cartItems) use ($buyable, $line, $quantity, $lineId, $purchaseContext, &$addedItem, &$isFirstItem): ?CartCollection {
            $addedItem = null;
            $isFirstItem = false;

            $this->assertBuyableOwner($buyable);
            $this->assertBuyableCurrency($buyable, $line);
            $this->rememberCartCurrency($line->currency);

            $existingItem = $cartItems->get($lineId);
            if ($existingItem instanceof CartItem) {
                $this->assertBuyableMatchesLine($buyable, $existingItem, $purchaseContext);
            }
            $lineQuantity = ($existingItem?->quantity ?? 0) + $quantity;
            $accumulatedQuantity = $this->accumulatedBuyableQuantity($cartItems, $buyable) + $quantity;

            $this->validateBuyable($buyable, $accumulatedQuantity);
            $this->validateContextualBuyable(
                $buyable,
                $purchaseContext,
                $this->accumulatedContextQuantity($cartItems, $buyable, $purchaseContext) + $quantity,
            );

            $pricing = $this->quoteBuyable($buyable, $lineQuantity, $purchaseContext);
            $attributes = $this->buildBuyableAttributes(
                $buyable,
                $line,
                $pricing,
                $existingItem?->attributes->toArray() ?? [],
                $purchaseContext,
            );

            $item = $this->createCartItem([
                'id' => $lineId,
                'name' => $line->name,
                'price' => $pricing->unitPrice,
                'quantity' => $lineQuantity,
                'attributes' => $attributes,
                'conditions' => $existingItem?->conditions->toArray() ?? [],
                'associated_model' => $buyable,
            ]);

            $isFirstItem = $cartItems->isEmpty();
            $cartItems->put($lineId, $item);
            $addedItem = $item;

            return $cartItems;
        });

        assert($addedItem instanceof CartItem);

        $this->invalidatePipelineCacheIfEnabled();
        $this->markDynamicConditionsDirty();

        if ($isFirstItem) {
            $this->dispatchEvent(new CartCreated($this));
        }

        $this->dispatchEvent(new ItemAdded($addedItem, $this));

        return $addedItem;
    }

    /**
     * Update a canonical buyable line to an absolute quantity.
     *
     * @param  array<string, mixed>  $extraAttributes
     *
     * @throws ProductNotPurchasableException When the accumulated quantity is invalid
     */
    public function updateBuyable(
        Buyable $buyable,
        int $newQuantity,
        ?string $lineIdentifier = null,
        array $extraAttributes = [],
        ?PurchaseContext $purchaseContext = null,
    ): ?CartItem {
        $lineId = $this->resolveBuyableLineIdentifier($buyable, $lineIdentifier, $purchaseContext);

        if ($newQuantity <= 0) {
            return $this->remove($lineId);
        }

        $line = BuyableLineData::fromBuyable($buyable, $newQuantity, $this->withoutCanonicalCompositeAttributes($extraAttributes));

        $updatedItem = null;

        $this->mutateItems(function (CartCollection $cartItems) use ($buyable, $line, $newQuantity, $lineId, $purchaseContext, &$updatedItem): ?CartCollection {
            $updatedItem = null;

            $this->assertBuyableOwner($buyable);
            $this->assertBuyableCurrency($buyable, $line);
            $this->rememberCartCurrency($line->currency);

            $existingItem = $cartItems->get($lineId);

            if (! $existingItem instanceof CartItem) {
                return null;
            }

            $this->assertBuyableMatchesLine($buyable, $existingItem, $purchaseContext);
            $accumulatedQuantity = $this->accumulatedBuyableQuantity($cartItems, $buyable, $lineId) + $newQuantity;

            $this->validateBuyable($buyable, $accumulatedQuantity);
            $this->validateContextualBuyable(
                $buyable,
                $purchaseContext,
                $this->accumulatedContextQuantity($cartItems, $buyable, $purchaseContext, $lineId) + $newQuantity,
            );

            $pricing = $this->quoteBuyable($buyable, $newQuantity, $purchaseContext);
            $attributes = $this->buildBuyableAttributes(
                $buyable,
                $line,
                $pricing,
                $existingItem->attributes->toArray(),
                $purchaseContext,
            );

            $item = $this->createCartItem([
                'id' => $lineId,
                'name' => $line->name,
                'price' => $pricing->unitPrice,
                'quantity' => $newQuantity,
                'attributes' => $attributes,
                'conditions' => $existingItem->conditions->toArray(),
                'associated_model' => $buyable,
            ]);

            $cartItems->put($lineId, $item);
            $updatedItem = $item;

            return $cartItems;
        });

        if (! $updatedItem instanceof CartItem) {
            return null;
        }

        $this->invalidatePipelineCacheIfEnabled();
        $this->markDynamicConditionsDirty();
        $this->dispatchEvent(new ItemUpdated($updatedItem, $this));

        return $updatedItem;
    }

    /**
     * Remove a canonical buyable line.
     */
    public function removeBuyable(Buyable $buyable, ?string $lineIdentifier = null, ?PurchaseContext $purchaseContext = null): ?CartItem
    {
        return $this->remove($this->resolveBuyableLineIdentifier($buyable, $lineIdentifier, $purchaseContext));
    }

    /**
     * Check whether a canonical buyable line exists.
     */
    public function hasBuyable(Buyable $buyable, ?string $lineIdentifier = null, ?PurchaseContext $purchaseContext = null): bool
    {
        return $this->has($this->resolveBuyableLineIdentifier($buyable, $lineIdentifier, $purchaseContext));
    }

    /**
     * Get a canonical buyable line.
     */
    public function getBuyable(Buyable $buyable, ?string $lineIdentifier = null, ?PurchaseContext $purchaseContext = null): ?CartItem
    {
        return $this->get($this->resolveBuyableLineIdentifier($buyable, $lineIdentifier, $purchaseContext));
    }

    /**
     * Validate that a buyable accepts the given total quantity.
     *
     * @throws ProductNotPurchasableException When validation fails
     */
    public function validateBuyable(Buyable $buyable, int $quantity): void
    {
        $productId = $buyable->getBuyableIdentifier();
        $productName = $buyable->getBuyableName();

        if (! $buyable->canBePurchased($quantity)) {
            $stock = $buyable->getBuyableStock();

            if ($stock !== null && $stock < $quantity) {
                throw ProductNotPurchasableException::outOfStock(
                    $productId,
                    $productName,
                    $quantity,
                    $stock,
                );
            }

            throw ProductNotPurchasableException::inactive($productId, $productName);
        }

        $minimumQuantity = $buyable->getBuyableMinimumQuantity();

        if ($quantity < $minimumQuantity) {
            throw ProductNotPurchasableException::minimumNotMet(
                $productId,
                $productName,
                $quantity,
                $minimumQuantity,
            );
        }

        $maximumQuantity = $buyable->getBuyableMaximumQuantity();

        if ($maximumQuantity !== null && $quantity > $maximumQuantity) {
            throw ProductNotPurchasableException::maximumExceeded(
                $productId,
                $productName,
                $quantity,
                $maximumQuantity,
            );
        }

        $increment = $buyable->getBuyableQuantityIncrement();

        if ($increment > 1 && ($quantity % $increment) !== 0) {
            throw ProductNotPurchasableException::invalidIncrement(
                $productId,
                $productName,
                $quantity,
                $increment,
            );
        }
    }

    /**
     * Refresh persisted buyable prices through the configured pricing policy.
     *
     * @param  callable(Buyable): ?Buyable  $resolver  Resolves a persisted line to a fresh buyable
     * @return array<string, array{old: int, new: int}> Price changes by line ID
     */
    public function refreshBuyablePrices(callable $resolver): array
    {
        $changes = [];
        $updatedItems = [];

        $this->mutateItems(function (CartCollection $cartItems) use ($resolver, &$changes, &$updatedItems): ?CartCollection {
            $changes = [];
            $updatedItems = [];

            foreach ($cartItems as $item) {
                $model = $item->getAssociatedModel();

                if (! $model instanceof Buyable) {
                    continue;
                }

                $fresh = $resolver($model);

                if (! $fresh instanceof Buyable) {
                    continue;
                }

                $itemContext = $this->contextForCartItem($item);
                $this->assertBuyableMatchesLine($fresh, $item, $itemContext);
                $this->assertBuyableOwner($fresh);
                $line = BuyableLineData::fromBuyable($fresh, $item->quantity);
                $this->assertBuyableCurrency($fresh, $line);
                $pricing = $this->quoteBuyable($fresh, $item->quantity, $itemContext);
                $attributes = $this->buildBuyableAttributes($fresh, $line, $pricing, $item->attributes->toArray(), $itemContext);
                $oldPrice = $item->price;

                if ($oldPrice === $pricing->unitPrice && $attributes === $item->attributes->toArray()) {
                    continue;
                }

                $updated = $item->with(['price' => $pricing->unitPrice, 'attributes' => $attributes, 'associated_model' => $fresh]);
                $cartItems->put($item->id, $updated);
                $updatedItems[$item->id] = $updated;

                if ($oldPrice !== $pricing->unitPrice) {
                    $changes[$item->id] = ['old' => $oldPrice, 'new' => $pricing->unitPrice];
                }
            }

            if ($updatedItems === []) {
                return null;
            }

            return $cartItems;
        });

        if ($updatedItems === []) {
            return $changes;
        }

        $this->invalidatePipelineCacheIfEnabled();
        $this->markDynamicConditionsDirty();

        foreach ($updatedItems as $updated) {
            $this->dispatchEvent(new ItemUpdated($updated, $this));
        }

        return $changes;
    }

    /**
     * Validate all persisted buyable lines against their accumulated quantities.
     *
     * @param  callable(Buyable): ?Buyable  $resolver  Resolves a persisted line to a fresh buyable
     * @return array<string, ProductNotPurchasableException> Validation errors by line ID
     */
    public function validateAllBuyables(callable $resolver): array
    {
        $errors = [];
        $entries = [];
        $totals = [];

        foreach ($this->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Buyable) {
                continue;
            }

            $fresh = $resolver($model);

            if (! $fresh instanceof Buyable) {
                $errors[$item->id] = ProductNotPurchasableException::inactive(
                    $item->id,
                    $item->name,
                );

                continue;
            }

            $key = $this->buyableMerchandiseKey($fresh);
            $entries[] = ['item' => $item, 'buyable' => $fresh];
            $totals[$key] = ($totals[$key] ?? 0) + $item->quantity;
        }

        foreach ($entries as $entry) {
            $key = $this->buyableMerchandiseKey($entry['buyable']);

            try {
                $this->validateBuyable($entry['buyable'], $totals[$key] ?? $entry['item']->quantity);
            } catch (ProductNotPurchasableException $exception) {
                $errors[$entry['item']->id] = $exception;
            }
        }

        return $errors;
    }

    /**
     * Get total weight of all items in the cart (for shipping).
     *
     * @return int Total weight in grams
     */
    public function getTotalWeight(): int
    {
        $totalWeight = 0;

        foreach ($this->getItems() as $item) {
            $weight = $item->getAttribute('weight');

            if ($weight !== null) {
                $totalWeight += (int) $weight * $item->quantity;
            }
        }

        return $totalWeight;
    }

    private function resolveBuyableLineIdentifier(
        Buyable $buyable,
        ?string $lineIdentifier,
        ?PurchaseContext $purchaseContext = null,
    ): string {
        $lineIdentifier = mb_trim((string) $lineIdentifier);

        if ($lineIdentifier !== '') {
            return $lineIdentifier;
        }

        if ($purchaseContext !== null && ! $purchaseContext->isEmpty()) {
            return 'ctx:' . hash('sha256', $buyable->getBuyableIdentifier() . ':' . $purchaseContext->fingerprint);
        }

        return $buyable->getBuyableIdentifier();
    }

    private function accumulatedContextQuantity(
        CartCollection $cartItems,
        Buyable $buyable,
        ?PurchaseContext $purchaseContext,
        ?string $excludeLineId = null,
    ): int {
        if ($purchaseContext === null) {
            return 0;
        }

        $key = $this->buyableMerchandiseKey($buyable);
        $quantity = 0;

        foreach ($cartItems as $item) {
            if ($excludeLineId !== null && $item->id === $excludeLineId) {
                continue;
            }

            if ($this->cartItemMerchandiseKey($item) !== $key) {
                continue;
            }

            $existingContext = $this->contextForCartItem($item);

            if ($existingContext?->fingerprint === $purchaseContext->fingerprint) {
                $quantity += $item->quantity;
            }
        }

        return $quantity;
    }

    private function contextForCartItem(CartItem $item): ?PurchaseContext
    {
        try {
            return PurchaseContext::fromAttributes($item->attributes->toArray());
        } catch (InvalidArgumentException $exception) {
            throw new InvalidCartItemException('Invalid purchase context on cart line [' . $item->id . ']: ' . $exception->getMessage());
        }
    }

    private function validateContextualBuyable(Buyable $buyable, ?PurchaseContext $context, int $quantity): void
    {
        if ($context === null || ! function_exists('app') || ! app()->bound(ContextualBuyableValidatorInterface::class)) {
            return;
        }

        $result = app(ContextualBuyableValidatorInterface::class)->validate($buyable, $quantity, $context);

        if (! $result->isValid) {
            throw new InvalidCartItemException($result->message ?? 'The selected purchase context is unavailable.');
        }
    }

    /**
     * Revalidates persisted selections, including context integrity, with
     * aggregate quantity across lines sharing the same resource selection.
     *
     * @return array<string, string>  Errors by cart line identifier
     */
    public function validateContextualBuyables(): array
    {
        $errors = [];
        $groups = [];
        $validatorBound = function_exists('app') && app()->bound(ContextualBuyableValidatorInterface::class);

        foreach ($this->getItems() as $item) {
            try {
                $context = $this->contextForCartItem($item);
            } catch (InvalidCartItemException $exception) {
                $errors[$item->id] = $exception->getMessage();

                continue;
            }

            // Context integrity is checked for every persisted selection, even
            // without a bound policy. Unbound policies must not prevent plain
            // (potentially non-Eloquent) Buyable records from being quoted.
            if ($context === null || ! $validatorBound) {
                continue;
            }

            $model = $item->getAssociatedModel();

            if (! $model instanceof Buyable) {
                $errors[$item->id] = 'The selected purchase context cannot be validated without its buyable.';

                continue;
            }

            $key = $this->buyableMerchandiseKey($model) . ':' . $context->fingerprint;

            if (! isset($groups[$key])) {
                $groups[$key] = ['buyable' => $model, 'context' => $context, 'quantity' => 0, 'items' => []];
            }

            $groups[$key]['quantity'] += $item->quantity;
            $groups[$key]['items'][] = $item->id;
        }

        if (! $validatorBound) {
            return $errors;
        }

        foreach ($groups as $group) {
            $result = app(ContextualBuyableValidatorInterface::class)->validate(
                $group['buyable'],
                $group['quantity'],
                $group['context'],
            );

            if (! $result->isValid) {
                foreach ($group['items'] as $lineId) {
                    $errors[$lineId] = $result->message ?? 'The selected purchase context is unavailable.';
                }
            }
        }

        return $errors;
    }

    private function accumulatedBuyableQuantity(
        CartCollection $cartItems,
        Buyable $buyable,
        ?string $excludeLineId = null,
    ): int {
        $key = $this->buyableMerchandiseKey($buyable);
        $quantity = 0;

        foreach ($cartItems as $item) {
            if ($excludeLineId !== null && $item->id === $excludeLineId) {
                continue;
            }

            if ($this->cartItemMerchandiseKey($item) === $key) {
                $quantity += $item->quantity;
            }
        }

        return $quantity;
    }

    private function assertBuyableMatchesLine(
        Buyable $buyable,
        CartItem $item,
        ?PurchaseContext $purchaseContext = null,
    ): void {
        if ($this->cartItemMerchandiseKey($item) !== $this->buyableMerchandiseKey($buyable)) {
            throw new InvalidCartItemException('The cart line identifier already belongs to another item.');
        }

        $existingContext = $this->contextForCartItem($item);

        if ($existingContext?->fingerprint !== $purchaseContext?->fingerprint
            && ! ($existingContext === null && $purchaseContext?->isEmpty())) {
            throw new InvalidCartItemException('A cart line cannot combine different purchase contexts.');
        }
    }

    private function buyableMerchandiseKey(Buyable $buyable): string
    {
        $type = $buyable instanceof Model ? $buyable->getMorphClass() : $buyable::class;

        return $type . ':' . $buyable->getBuyableIdentifier();
    }

    private function cartItemMerchandiseKey(CartItem $item): ?string
    {
        $type = $item->getAttribute('purchasable_type');
        $id = $item->getAttribute('purchasable_id');

        if (is_string($type) && $type !== '' && (is_string($id) || is_int($id)) && (string) $id !== '') {
            return $type . ':' . (string) $id;
        }

        $model = $item->getAssociatedModel();

        if ($model instanceof Model) {
            return $model->getMorphClass() . ':' . (string) $model->getKey();
        }

        if (is_object($model)) {
            $modelId = $model->id ?? null;

            if (is_string($modelId) || is_int($modelId)) {
                return $model::class . ':' . (string) $modelId;
            }
        }

        return null;
    }

    private function quoteBuyable(Buyable $buyable, int $quantity, ?PurchaseContext $purchaseContext = null): BuyablePriceData
    {
        $cartCurrency = $this->cartCurrency();

        if (function_exists('app') && app()->bound(BuyablePriceResolver::class)) {
            $pricing = app(BuyablePriceResolver::class)->resolve(
                $buyable,
                $quantity,
                $this->buyablePricingContext($cartCurrency, $purchaseContext),
            );
        } else {
            $pricing = new BuyablePriceData(
                unitPrice: $buyable->getBuyablePrice(),
                currency: $buyable->getBuyableCurrency(),
            );
        }

        $pricingCurrency = mb_strtoupper(mb_trim($pricing->currency));

        if ($pricingCurrency === '' || $pricingCurrency !== $cartCurrency) {
            throw new InvalidCartItemException(sprintf(
                'Buyable currency [%s] does not match the cart currency [%s].',
                $pricingCurrency,
                $cartCurrency,
            ));
        }

        if ($pricing->unitPrice < 0) {
            throw new InvalidCartItemException('Buyable unit price cannot be negative.');
        }

        return $pricing;
    }

    /**
     * @return array<string, mixed>
     */
    private function buyablePricingContext(string $currency, ?PurchaseContext $purchaseContext = null): array
    {
        $context = ['currency' => $currency];

        if ($purchaseContext !== null) {
            $context['purchase_context'] = $purchaseContext->values;
            $context['purchase_context_fingerprint'] = $purchaseContext->fingerprint;
        }
        $customerId = $this->getMetadata('customer_id');

        if (is_string($customerId) || is_int($customerId)) {
            $context['customer_id'] = $customerId;
        }

        $customerType = $this->getMetadata('customer_type');

        if (is_string($customerType) && $customerType !== '') {
            $context['customer_type'] = $customerType;
        }

        return $context;
    }

    private function cartCurrency(): string
    {
        $currency = $this->getMetadata('currency');

        return CartMoney::currency(is_string($currency) ? $currency : null);
    }

    private function rememberCartCurrency(string $currency): void
    {
        $this->storage->mutateMetadata(
            $this->getIdentifier(),
            $this->instance(),
            'currency',
            static fn (mixed $current): string => is_string($current) && $current !== '' ? $current : $currency,
        );
    }

    private function assertBuyableCurrency(Buyable $buyable, BuyableLineData $line): void
    {
        $cartCurrency = $this->cartCurrency();
        $buyableCurrency = mb_strtoupper(mb_trim($buyable->getBuyableCurrency()));

        if ($buyableCurrency === '' || $buyableCurrency !== $cartCurrency) {
            throw new InvalidCartItemException(sprintf(
                'Buyable currency [%s] does not match the cart currency [%s].',
                $buyableCurrency,
                $cartCurrency,
            ));
        }

        if ($line->currency !== $cartCurrency) {
            throw new InvalidCartItemException(sprintf(
                'Buyable line currency [%s] does not match the cart currency [%s].',
                $line->currency,
                $cartCurrency,
            ));
        }
    }

    private function assertBuyableOwner(Buyable $buyable): void
    {
        if (! (bool) config('cart.owner.enabled', false) || ! $buyable instanceof Model) {
            return;
        }

        $attributes = $buyable->getAttributes();

        if (! array_key_exists('owner_type', $attributes) || ! array_key_exists('owner_id', $attributes)) {
            return;
        }

        $ownerType = $buyable->getAttribute('owner_type');
        $ownerId = $buyable->getAttribute('owner_id');
        $cartOwnerType = $this->storage->getOwnerType();
        $cartOwnerId = $this->storage->getOwnerId();

        if ($ownerType === null && $ownerId === null) {
            if ($cartOwnerType === null && $cartOwnerId === null) {
                return;
            }

            if ((bool) config('cart.owner.include_global', false)) {
                return;
            }

            throw new InvalidCartItemException('A global buyable cannot be added to an owner-scoped cart.');
        }

        if ($ownerType === $cartOwnerType && (string) $ownerId === (string) $cartOwnerId) {
            return;
        }

        throw new InvalidCartItemException('A buyable owned by another owner cannot be added to this cart.');
    }

    /**
     * @param  array<string, mixed>  $existingAttributes
     * @return array<string, mixed>
     */
    private function buildBuyableAttributes(
        Buyable $buyable,
        BuyableLineData $line,
        BuyablePriceData $pricing,
        array $existingAttributes,
        ?PurchaseContext $purchaseContext = null,
    ): array {
        $type = $buyable instanceof Model ? $buyable->getMorphClass() : $buyable::class;
        $catalogId = $line->catalogIdentifier ?? $line->identifier;
        $existingAttributes = array_diff_key($existingAttributes, array_flip([
            'components', 'components_fingerprint', 'original_unit_price', 'discount_amount',
            'discount_source', 'price_list', 'tier', 'promotion', 'pricing_breakdown',
            PurchaseContext::ATTRIBUTE_CONTEXT, PurchaseContext::ATTRIBUTE_FINGERPRINT,
        ]));
        $attributes = array_merge($existingAttributes, $line->attributes, [
            'purchasable_type' => $type,
            'purchasable_id' => $line->identifier,
            'catalog_id' => $catalogId,
            'currency' => $line->currency,
            'product_id' => $catalogId,
        ]);

        if ($purchaseContext !== null) {
            $attributes[PurchaseContext::ATTRIBUTE_CONTEXT] = $purchaseContext->values;
            $attributes[PurchaseContext::ATTRIBUTE_FINGERPRINT] = $purchaseContext->fingerprint;
        }

        if (array_key_exists('components', $attributes)) {
            $attributes['components'] = CompositeComponents::read($attributes['components']);
        }

        if ($catalogId !== $line->identifier) {
            $attributes['variant_id'] = $line->identifier;
        }

        if ($pricing->originalUnitPrice !== null) {
            $attributes['original_unit_price'] = $pricing->originalUnitPrice;
            $attributes['discount_amount'] = $pricing->discountAmount;

            if ($pricing->discountSource !== null) {
                $attributes['discount_source'] = $pricing->discountSource;
            }

            if ($pricing->priceListName !== null) {
                $attributes['price_list'] = $pricing->priceListName;
            }

            if ($pricing->tierDescription !== null) {
                $attributes['tier'] = $pricing->tierDescription;
            }

            if ($pricing->promotionName !== null) {
                $attributes['promotion'] = $pricing->promotionName;
            }

            if ($pricing->breakdown !== []) {
                $attributes['pricing_breakdown'] = $pricing->breakdown;
            }
        }

        return $attributes;
    }
}
