<?php

declare(strict_types=1);

namespace AIArmada\Cart\Snapshots;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Conditions\CartCondition as BaseCartCondition;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipeline;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Events\CartSnapshotSynced;
use AIArmada\Cart\Events\HighValueCartDetected;
use AIArmada\Cart\Models\CartItem as BaseCartItem;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Throwable;

class NormalizedCartSynchronizer
{
    public function syncFromCart(BaseCart $cart): void
    {
        // Concurrent first-syncs race the snapshot unique key; the loser retries
        // once in a fresh transaction, where firstOrNew() finds the winner's row.
        try {
            DB::transaction(fn () => $this->syncFromCartInTransaction($cart));
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            DB::transaction(fn () => $this->syncFromCartInTransaction($cart));
        }
    }

    private function syncFromCartInTransaction(BaseCart $cart): void
    {
        $identifier = $cart->getIdentifier();
        $instance = $cart->instance();

        $owner = CartSnapshot::resolveCurrentOwner();

        $items = $cart->getItems();
        $conditions = $cart->getStoredConditions();
        $metadata = $cart->getAllMetadata();

        $currency = $this->resolveCurrency();

        if (CartSnapshot::ownerScopingEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                CartSnapshot::class . ' requires an owner context or explicit global context.',
            );
        }

        $cartModel = CartSnapshot::query()->forOwner($owner)->firstOrNew([
            'identifier' => $identifier,
            'instance' => $instance,
        ]);

        $previousTotal = $cartModel->exists ? (int) $cartModel->getOriginal('total') : 0;

        if (CartSnapshot::ownerScopingEnabled() && $owner !== null) {
            $cartModel->assignOwner($owner);
        }

        $cartModel->items = $items->isEmpty() ? null : $items->toArray();
        $cartModel->conditions = $conditions->isEmpty() ? null : $conditions->toArray();
        $cartModel->metadata = $metadata === [] ? null : $metadata;
        $cartModel->items_count = $cart->countItems();
        $cartModel->quantity = $cart->getTotalQuantity();
        $cartModel->last_activity_at = $this->resolveLastActivityAt($cart, $metadata, $cartModel->last_activity_at);
        $cartModel->checkout_started_at = $this->resolveMetadataTimestamp($metadata, 'checkout_started_at', $cartModel->checkout_started_at);
        $cartModel->checkout_abandoned_at = $this->resolveMetadataTimestamp($metadata, 'checkout_abandoned_at', $cartModel->checkout_abandoned_at);
        $pipeline = new ConditionPipeline;
        $pipelineResult = $pipeline->process(new ConditionPipelineContext($cart, $conditions));

        $subtotalWithoutConditions = (int) $cart->subtotalWithoutConditions()->getAmount();
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

        if ($hasMaterialChanges) {
            event(CartSnapshotSynced::fromCart($cartModel));
        }

        $highValueThreshold = (int) config('cart.snapshots.analytics.high_value_threshold_minor', 10000);

        if ($highValueThreshold > 0 && $previousTotal < $highValueThreshold && $cartModel->total >= $highValueThreshold) {
            event(HighValueCartDetected::fromCart($cartModel));
        }

        if (! $itemsDirty && ! $isNew && $items->isEmpty() && $this->hasChildRows(CartSnapshotItem::class, $cartModel)) {
            $itemsDirty = true;
        }

        if (! $conditionsDirty && ! $isNew && $conditions->isEmpty() && $this->hasChildRows(CartSnapshotCondition::class, $cartModel)) {
            $conditionsDirty = true;
        }

        if ($itemsDirty || $conditionsDirty) {
            $itemModels = $itemsDirty
                ? $this->syncItems($cartModel, $items)
                : $this->existingItemModels($cartModel);

            $this->syncConditions($cartModel, $conditions, $itemModels, $items->all());
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($code, ['23000', '23505'], true);
    }

    /**
     * Delete the normalized cart representation from database
     * This is called when the cart is destroyed or cleared
     */
    public function deleteNormalizedCart(
        string $identifier,
        string $instance,
        ?string $ownerType = null,
        string | int | null $ownerId = null,
    ): void {
        $hasExplicitOwnerTuple = func_num_args() >= 3;

        $owner = $hasExplicitOwnerTuple
            ? OwnerContext::fromTypeAndId($ownerType, $ownerId)
            : CartSnapshot::resolveCurrentOwner();

        $runDelete = function (?EloquentModel $scopedOwner) use ($identifier, $instance): void {
            $cartModel = CartSnapshot::query()->forOwner($scopedOwner)
                ->where('identifier', $identifier)
                ->where('instance', $instance)
                ->first();

            if (! $cartModel) {
                return;
            }

            $this->assertSnapshotScope($cartModel);

            CartSnapshotCondition::query()->where('cart_id', $cartModel->id)->delete();
            CartSnapshotItem::query()->where('cart_id', $cartModel->id)->delete();
            $cartModel->delete();
        };

        if (CartSnapshot::ownerScopingEnabled() && $hasExplicitOwnerTuple && $ownerType === null && $ownerId === null) {
            OwnerContext::withOwner(null, function () use ($runDelete): void {
                $runDelete(null);
            });

            return;
        }

        if (CartSnapshot::ownerScopingEnabled() && $hasExplicitOwnerTuple) {
            OwnerContext::withOwner($owner, function () use ($owner, $runDelete): void {
                $runDelete($owner);
            });

            return;
        }

        if (CartSnapshot::ownerScopingEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                CartSnapshot::class . ' requires an owner context or explicit global context.',
            );
        }

        $runDelete($owner);
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
     * @return array<string, CartSnapshotItem>
     */
    private function existingItemModels(CartSnapshot $cartModel): array
    {
        $this->assertSnapshotScope($cartModel);

        return CartSnapshotItem::query()
            ->where('cart_id', $cartModel->id)
            ->get()
            ->keyBy('item_id')
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

    /**
     * @param  Collection<int, BaseCartItem>  $items
     * @return array<string, CartSnapshotItem>
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
        $itemIds = [];

        foreach ($items as $item) {
            \assert($item instanceof BaseCartItem);

            $attributes = $item->attributes->toArray();
            $conditions = $item->conditions->isEmpty() ? null : $item->conditions->toArray();

            $existingItem = $existing->get($item->id);
            $itemIds[] = $item->id;
            $rows[] = [
                'id' => $existingItem?->id ?? (string) Str::uuid(),
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
            ->whereNotIn('item_id', $itemIds)
            ->delete();

        return CartSnapshotItem::query()
            ->where('cart_id', $cartModel->id)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id')
            ->all();
    }

    /**
     * @param  Collection<int, BaseCartCondition>  $conditions
     * @param  array<string, CartSnapshotItem>  $itemModels
     * @param  array<int, BaseCartItem>  $originalItems
     */
    private function syncConditions(CartSnapshot $cartModel, Collection $conditions, array $itemModels, array $originalItems): void
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

            if (! isset($itemModels[$item->id])) {
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
                    cartItemModel: $itemModels[$item->id],
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
        ?CartSnapshotItem $cartItemModel,
        ?string $itemId,
        CarbonImmutable $now,
    ): void {
        $key = $this->conditionKey($condition->getName(), $itemId, $cartItemModel?->id);
        $existingCondition = $existing->get($key);
        $id = (string) ($existingCondition?->getKey() ?? Str::uuid());
        $persistedIds[] = $id;
        $data = $condition->toArray();
        $targetDefinition = $condition->getTargetDefinition();

        $rows[] = [
            'id' => $id,
            'cart_id' => $cartModel->id,
            'cart_item_id' => $cartItemModel?->id,
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
    private function resolveLastActivityAt(BaseCart $cart, array $metadata, ?DateTimeInterface $existingLastActivityAt): ?Carbon
    {
        if (array_key_exists('last_activity_at', $metadata)) {
            return $this->parseTimestamp($metadata['last_activity_at']);
        }

        return $this->parseTimestamp($cart->getUpdatedAt())
            ?? $this->parseTimestamp($existingLastActivityAt)
            ?? $this->parseTimestamp($cart->getCreatedAt());
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveMetadataTimestamp(array $metadata, string $key, ?DateTimeInterface $existingValue): ?Carbon
    {
        if (array_key_exists($key, $metadata)) {
            return $this->parseTimestamp($metadata[$key]);
        }

        return $this->parseTimestamp($existingValue);
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
