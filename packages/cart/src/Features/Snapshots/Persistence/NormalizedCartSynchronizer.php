<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Persistence;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Conditions\CartCondition as BaseCartCondition;
use AIArmada\Cart\Data\CartSnapshotData;
use AIArmada\Cart\Events\CartSnapshotSynced;
use AIArmada\Cart\Events\HighValueCartDetected;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotCondition;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotItem;
use AIArmada\Cart\Models\CartItem as BaseCartItem;
use AIArmada\Cart\Runtime\CartOwnerIdentity;
use AIArmada\Cart\Storage\CartStorageSnapshot;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Throwable;

class NormalizedCartSynchronizer
{
    public function syncFromCart(BaseCart $cart): void
    {
        $cart->evaluateDynamicConditionsIfDirty();

        $storageSnapshot = $cart->storageSnapshot();

        if ($storageSnapshot === null) {
            $this->deleteNormalizedCart($cart->getIdentifier(), $cart->instance());

            return;
        }

        // Concurrent first-syncs race the snapshot unique key; the loser retries
        // once in a fresh transaction, where firstOrNew() finds the winner's row.
        try {
            $events = (new CartSnapshot)->getConnection()->transaction(fn (): array => $this->syncFromCartInTransaction($cart, $storageSnapshot));
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $events = (new CartSnapshot)->getConnection()->transaction(fn (): array => $this->syncFromCartInTransaction($cart, $storageSnapshot));
        }

        $this->dispatchSnapshotEvents($events);
    }

    /**
     * @return array{snapshot: ?CartSnapshotData, highValue: ?CartSnapshotData}
     */
    private function syncFromCartInTransaction(BaseCart $cart, CartStorageSnapshot $storageSnapshot): array
    {
        $identifier = $cart->getIdentifier();
        $instance = $cart->instance();

        $owner = $this->resolveSourceOwner($cart);

        $items = $cart->itemsFromStorageSnapshot($storageSnapshot);
        $conditions = $cart->conditionsFromStorageSnapshot($storageSnapshot);
        $metadata = $storageSnapshot->metadata;

        $currency = $this->resolveCurrency();

        $cartModel = CartSnapshot::query()->forOwner($owner, false)->lockForUpdate()->firstOrNew([
            'identifier' => $identifier,
            'instance' => $instance,
        ]);

        $previousTotal = $cartModel->exists ? (int) $cartModel->getOriginal('total') : 0;

        if (CartSnapshot::ownerScopingEnabled() && $owner !== null) {
            $cartModel->assignOwner($owner);
        }

        $initialAmount = (int) $items->sum(
            static fn (BaseCartItem $item): int => $item->getRawSubtotal()
        );
        $subtotalWithoutConditions = (int) $items->sum(
            static fn (BaseCartItem $item): int => $item->getRawSubtotalWithoutConditions()
        );

        $cartModel->items = $items->isEmpty() ? null : $items->toArray();
        $cartModel->conditions = $conditions->isEmpty() ? null : $conditions->toArray();
        $cartModel->metadata = $metadata === [] ? null : $metadata;
        $cartModel->items_count = $items->count();
        $cartModel->quantity = (int) $items->sum('quantity');
        $cartModel->last_activity_at = $this->resolveLastActivityAt($metadata, $cartModel->last_activity_at, $storageSnapshot);
        $cartModel->checkout_started_at = $this->resolveMetadataTimestamp($metadata, 'checkout_started_at', $cartModel->checkout_started_at);
        $cartModel->checkout_abandoned_at = $this->resolveMetadataTimestamp($metadata, 'checkout_abandoned_at', $cartModel->checkout_abandoned_at);
        $pipelineResult = $cart->evaluateConditionPipeline(conditions: $conditions, initialAmount: $initialAmount);

        $cartModel->subtotal = $subtotalWithoutConditions;
        $cartModel->total = $pipelineResult->total();
        $cartModel->savings = max(0, $subtotalWithoutConditions - $cartModel->total);
        $cartModel->currency = $currency;
        $isNew = ! $cartModel->exists;
        $itemsDirty = $isNew || $cartModel->isDirty(['items', 'items_count', 'quantity']);
        $conditionsDirty = $isNew || $cartModel->isDirty(['conditions']);
        $hasMaterialChanges = $isNew || $cartModel->isDirty([
            'items',
            'conditions',
            'metadata',
            'items_count',
            'quantity',
            'subtotal',
            'total',
            'savings',
            'currency',
            'last_activity_at',
            'checkout_started_at',
            'checkout_abandoned_at',
        ]);
        $cartModel->save();

        if (! $itemsDirty && ! $isNew && $items->isEmpty() && $this->hasChildRows(CartSnapshotItem::class, $cartModel)) {
            $itemsDirty = true;
        }

        $cleanupCartLevelConditions = false;

        if (! $conditionsDirty && ! $isNew && $conditions->isEmpty() && $this->hasCartLevelChildRows($cartModel)) {
            $cleanupCartLevelConditions = true;
        }

        if ($itemsDirty || $conditionsDirty || $cleanupCartLevelConditions) {
            $itemIdMap = $itemsDirty
                ? $this->syncItems($cartModel, $items)
                : $this->itemIdMap($cartModel);

            if ($cleanupCartLevelConditions) {
                CartSnapshotCondition::query()
                    ->where('cart_id', $cartModel->id)
                    ->whereNull('cart_item_id')
                    ->delete();
            }

            if ($itemsDirty || $conditionsDirty) {
                $this->syncConditions($cartModel, $conditions, $itemIdMap, $items->all());
            }
        }

        $snapshotData = $cartModel->toSnapshotData();
        $highValueData = null;
        $highValueThreshold = (int) config('cart.snapshots.analytics.high_value_threshold_minor', 10000);

        if ($highValueThreshold > 0 && $previousTotal < $highValueThreshold && $cartModel->total >= $highValueThreshold) {
            $highValueData = $snapshotData;
        }

        return [
            'snapshot' => $hasMaterialChanges ? $snapshotData : null,
            'highValue' => $highValueData,
        ];
    }

    /**
     * @param  array{snapshot: ?CartSnapshotData, highValue: ?CartSnapshotData}  $events
     */
    private function dispatchSnapshotEvents(array $events): void
    {
        if ($events['snapshot'] !== null) {
            event(CartSnapshotSynced::fromSnapshot($events['snapshot']));
        }

        if ($events['highValue'] !== null) {
            event(HighValueCartDetected::fromSnapshot($events['highValue']));
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($code, ['23000', '23505'], true);
    }

    /**
     * Derive the owner scope that owns the source cart and validate it.
     *
     * The snapshot must be written under the owner of the source cart's
     * storage, never under whatever ambient owner happens to be active when
     * the sync runs (listeners, queued jobs, admin tooling). A global source
     * cart requires explicit global context; an owned source cart requires an
     * ambient owner that matches the source tuple.
     *
     * @throws AuthorizationException When the ambient context does not match the source cart owner
     */
    private function resolveSourceOwner(BaseCart $cart): ?EloquentModel
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return null;
        }

        $storage = $cart->storage();
        $ownerType = $storage->getOwnerType();
        $ownerId = $storage->getOwnerId();

        if ($ownerType === null && $ownerId === null) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                null,
                CartSnapshot::class . ' requires an owner context or explicit global context.',
            );

            return null;
        }

        $owner = OwnerContext::fromTypeAndId($ownerType, $ownerId);
        $ambient = OwnerContext::resolve();

        if ($ambient === null
            || $ambient->getMorphClass() !== $ownerType
            || (string) $ambient->getKey() !== (string) $ownerId
        ) {
            throw new AuthorizationException(
                'Cart snapshot can only be synchronized inside the owner scope of the source cart.'
            );
        }

        return $owner;
    }

    /**
     * Delete the normalized cart representation from database
     * This is called when the cart is destroyed or cleared
     */
    public function deleteNormalizedCart(
        string $identifier,
        string $instance,
        ?CartOwnerIdentity $identity = null,
    ): void {
        if (! CartSnapshot::ownerScopingEnabled()) {
            $this->deleteSnapshotRow($identifier, $instance, null);

            return;
        }

        if ($identity === null) {
            $owner = CartSnapshot::resolveCurrentOwner();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                CartSnapshot::class . ' requires an owner context or explicit global context.',
            );

            OwnerContext::withOwner($owner, fn () => $this->deleteSnapshotRow($identifier, $instance, $owner));

            return;
        }

        $owner = OwnerContext::fromTypeAndId($identity->ownerType, $identity->ownerId);

        if ($identity->isGlobal()) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Global cart snapshot deletion requires explicit global owner context.');
            }
        } else {
            $ambient = OwnerContext::resolve();

            if ($ambient === null
                || $ambient->getMorphClass() !== $identity->ownerType
                || (string) $ambient->getKey() !== (string) $identity->ownerId
            ) {
                throw new AuthorizationException('Cart snapshot can only be deleted inside the owner scope of the source cart.');
            }
        }

        OwnerContext::withOwner($owner, fn () => $this->deleteSnapshotRow($identifier, $instance, $owner));
    }

    private function deleteSnapshotRow(string $identifier, string $instance, ?EloquentModel $scopedOwner): void
    {
        (new CartSnapshot)->getConnection()->transaction(function () use ($identifier, $instance, $scopedOwner): void {
            $cartModel = CartSnapshot::query()->forOwner($scopedOwner, false)
                ->where('identifier', $identifier)
                ->where('instance', $instance)
                ->lockForUpdate()
                ->first();

            if (! $cartModel) {
                return;
            }

            $this->assertSnapshotScope($cartModel);

            CartSnapshotCondition::query()->where('cart_id', $cartModel->id)->delete();
            CartSnapshotItem::query()->where('cart_id', $cartModel->id)->delete();
            $cartModel->delete();
        });
    }

    /**
     * Child item/condition rows carry no owner tuple of their own; they are scoped
     * exclusively through the owner-scoped parent snapshot. This assertion makes
     * that invariant explicit so child writes fail loudly if the parent ever
     * resolves outside the current owner context.
     *
     * @throws AuthorizationException
     */
    private function assertSnapshotScope(CartSnapshot $cartModel): void
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return;
        }

        $owner = OwnerContext::resolve();
        $expectedType = $owner?->getMorphClass();
        $expectedId = $owner !== null ? (string) $owner->getKey() : null;
        $actualId = $cartModel->owner_id !== null ? (string) $cartModel->owner_id : null;

        if ($cartModel->owner_type !== $expectedType || $actualId !== $expectedId) {
            throw new AuthorizationException('Cart snapshot is not accessible in the current owner scope.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function itemIdMap(CartSnapshot $cartModel): array
    {
        $this->assertSnapshotScope($cartModel);

        return CartSnapshotItem::query()
            ->where('cart_id', $cartModel->id)
            ->pluck('id', 'item_id')
            ->all();
    }

    /**
     * @param  class-string<CartSnapshotItem|CartSnapshotCondition>  $model
     */
    private function hasChildRows(string $model, CartSnapshot $cartModel): bool
    {
        $this->assertSnapshotScope($cartModel);

        return $model::query()->where('cart_id', $cartModel->id)->exists();
    }

    private function hasCartLevelChildRows(CartSnapshot $cartModel): bool
    {
        $this->assertSnapshotScope($cartModel);

        return CartSnapshotCondition::query()
            ->where('cart_id', $cartModel->id)
            ->whereNull('cart_item_id')
            ->exists();
    }

    /**
     * @param  Collection<int, BaseCartItem>  $items
     * @return array<string, string>
     */
    private function syncItems(CartSnapshot $cartModel, Collection $items): array
    {
        $this->assertSnapshotScope($cartModel);

        $existing = CartSnapshotItem::query()
            ->where('cart_id', $cartModel->id)
            ->get()
            ->keyBy('item_id');

        if ($items->isEmpty()) {
            CartSnapshotItem::query()->where('cart_id', $cartModel->id)->delete();

            return [];
        }

        $now = CarbonImmutable::now();
        $rows = [];
        $persistedIds = [];

        foreach ($items as $item) {
            \assert($item instanceof BaseCartItem);

            $attributes = $item->attributes->toArray();
            $conditions = $item->conditions->isEmpty() ? null : $item->conditions->toArray();

            $existingItem = $existing->get($item->id);
            $id = (string) ($existingItem?->id ?? Str::uuid());
            $persistedIds[] = $id;
            $rows[] = [
                'id' => $id,
                'cart_id' => $cartModel->id,
                'item_id' => $item->id,
                'name' => $item->name,
                'price' => (int) $item->getRawPriceWithoutConditions(),
                'quantity' => $item->quantity,
                'attributes' => $this->encodeJson($attributes),
                'conditions' => $this->encodeJson($conditions),
                'associated_model' => $this->resolveAssociatedModel($item->associatedModel),
                'created_at' => $existingItem?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        CartSnapshotItem::query()->upsert(
            $rows,
            ['id'],
            ['cart_id', 'item_id', 'name', 'price', 'quantity', 'attributes', 'conditions', 'associated_model', 'updated_at'],
        );

        CartSnapshotItem::query()
            ->where('cart_id', $cartModel->id)
            ->whereNotIn('id', $persistedIds)
            ->delete();

        $itemIdMap = [];

        foreach ($rows as $row) {
            $itemIdMap[$row['item_id']] = $row['id'];
        }

        return $itemIdMap;
    }

    /**
     * @param  Collection<int, BaseCartCondition>  $conditions
     * @param  array<string, string>  $itemIds
     * @param  array<int, BaseCartItem>  $originalItems
     */
    private function syncConditions(CartSnapshot $cartModel, Collection $conditions, array $itemIds, array $originalItems): void
    {
        $this->assertSnapshotScope($cartModel);

        $existing = CartSnapshotCondition::query()
            ->where('cart_id', $cartModel->id)
            ->get()
            ->keyBy(fn (CartSnapshotCondition $condition): string => $this->conditionKey(
                $condition->name,
                $condition->item_id,
                $condition->cart_item_id,
            ));

        $rows = [];
        $persistedIds = [];
        $now = CarbonImmutable::now();

        foreach ($conditions as $condition) {
            \assert($condition instanceof BaseCartCondition);
            $this->queueConditionRow($rows, $persistedIds, $existing, $cartModel, $condition, null, null, $now);
        }

        foreach ($originalItems as $item) {
            \assert($item instanceof BaseCartItem);

            if (! isset($itemIds[$item->id])) {
                continue;
            }

            foreach ($item->conditions as $condition) {
                \assert($condition instanceof BaseCartCondition);
                $this->queueConditionRow(
                    rows: $rows,
                    persistedIds: $persistedIds,
                    existing: $existing,
                    cartModel: $cartModel,
                    condition: $condition,
                    cartItemId: $itemIds[$item->id],
                    itemId: $item->id,
                    now: $now,
                );
            }
        }

        if ($rows !== []) {
            CartSnapshotCondition::query()->upsert(
                $rows,
                ['id'],
                [
                    'cart_id', 'cart_item_id', 'name', 'item_id', 'type', 'target',
                    'target_definition', 'value', 'order', 'attributes', 'operator',
                    'is_charge', 'is_dynamic', 'is_discount', 'is_percentage',
                    'is_global', 'parsed_value', 'rules', 'updated_at',
                ],
            );
        }

        $query = CartSnapshotCondition::query()->where('cart_id', $cartModel->id);
        if ($persistedIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('id', $persistedIds)->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $persistedIds
     * @param  Collection<string, CartSnapshotCondition>  $existing
     */
    private function queueConditionRow(
        array &$rows,
        array &$persistedIds,
        Collection $existing,
        CartSnapshot $cartModel,
        BaseCartCondition $condition,
        ?string $cartItemId,
        ?string $itemId,
        CarbonImmutable $now,
    ): void {
        $key = $this->conditionKey($condition->getName(), $itemId, $cartItemId);
        $existingCondition = $existing->get($key);
        $id = (string) ($existingCondition?->getKey() ?? Str::uuid());
        $persistedIds[] = $id;
        $data = $condition->toArray();
        $targetDefinition = $condition->getTargetDefinition();

        $rows[] = [
            'id' => $id,
            'cart_id' => $cartModel->id,
            'cart_item_id' => $cartItemId,
            'name' => $condition->getName(),
            'item_id' => $itemId,
            'type' => $data['type'],
            'target' => $targetDefinition->toDsl(),
            'target_definition' => $this->encodeJson($targetDefinition->toArray()),
            'value' => (string) $data['value'],
            'order' => $data['order'],
            'attributes' => $this->encodeJson(Arr::get($data, 'attributes')),
            'operator' => $data['operator'] ?? null,
            'is_charge' => (bool) ($data['is_charge'] ?? false),
            'is_dynamic' => (bool) ($data['is_dynamic'] ?? false),
            'is_discount' => (bool) ($data['is_discount'] ?? false),
            'is_percentage' => (bool) ($data['is_percentage'] ?? false),
            'is_global' => (bool) ($data['attributes']['is_global'] ?? false),
            'parsed_value' => isset($data['parsed_value']) ? (string) $data['parsed_value'] : null,
            'rules' => $this->encodeJson($data['rules'] ?? null),
            'created_at' => $existingCondition?->created_at ?? $now,
            'updated_at' => $now,
        ];
    }

    private function conditionKey(string $name, ?string $itemId = null, ?string $cartItemId = null): string
    {
        return $cartItemId === null ? "cart:{$name}" : "item:{$cartItemId}:{$itemId}:{$name}";
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Snapshot data must be JSON serializable.', previous: $exception);
        }
    }

    private function resolveCurrency(): string
    {
        return CartMoney::currency();
    }

    private function resolveAssociatedModel(string | object | null $associatedModel): ?string
    {
        if (is_string($associatedModel)) {
            return $associatedModel;
        }

        return $associatedModel ? get_class($associatedModel) : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveLastActivityAt(array $metadata, ?DateTimeInterface $existingLastActivityAt, CartStorageSnapshot $storageSnapshot): ?CarbonImmutable
    {
        if (array_key_exists('last_activity_at', $metadata)) {
            return $this->parseTimestamp($metadata['last_activity_at']);
        }

        return $this->parseTimestamp($existingLastActivityAt)
            ?? $this->parseTimestamp($storageSnapshot->updatedAt)
            ?? $this->parseTimestamp($storageSnapshot->createdAt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveMetadataTimestamp(array $metadata, string $key, ?DateTimeInterface $existingValue): ?CarbonImmutable
    {
        if (array_key_exists($key, $metadata)) {
            return $this->parseTimestamp($metadata[$key]);
        }

        return $this->parseTimestamp($existingValue);
    }

    private function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
