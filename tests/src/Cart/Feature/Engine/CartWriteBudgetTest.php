<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Features\Snapshots\EntryPoints\Listeners\SyncCartOnEvent;
use AIArmada\Cart\Runtime\CartSnapshotSyncInterface;
use AIArmada\Cart\Services\BuiltInRulesFactory;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('writes the merged target exactly once before consuming the source', function (bool $existingTarget): void {
    $this->useEngine();

    $suffix = Str::lower(Str::random(12));
    $source = $this->engineStorage();
    $target = $this->engineStorage($this->engineOwner);
    $sourceIdentifier = 'write-source-' . $suffix;
    $targetIdentifier = 'write-target-' . $suffix;
    $items = ['item' => ['id' => 'item', 'quantity' => 2, 'price' => 1000]];
    $conditions = ['discount' => ['name' => 'discount']];
    $sourceRevision = $source->putItems($sourceIdentifier, 'default', $items, CartRevision::none());

    if ($existingTarget) {
        $target->putMetadataBatch($targetIdentifier, 'default', ['keep' => 'target', 'remove' => true]);
    }

    $targetRevision = $target->getSnapshot($targetIdentifier, 'default')?->revision() ?? CartRevision::none();
    $connection = DB::connection($this->engineConnection);
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $target->commitMergeTransfer(
            $source,
            $sourceIdentifier,
            $targetIdentifier,
            'default',
            $items,
            $conditions,
            ['remove' => null, 'new' => 'source'],
            $sourceRevision,
            $targetRevision,
        );
        $writes = array_filter($connection->getQueryLog(), static fn (array $query): bool => preg_match('/^(insert into|update) "carts"/i', $query['query']) === 1);
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }

    $merged = $target->getSnapshot($targetIdentifier, 'default');
    expect($writes)->toHaveCount(2)
        ->and($merged?->version)->toBe(($targetRevision->version ?? 0) + 1)
        ->and($merged?->items)->toEqual($items)
        ->and($merged?->conditions)->toEqual($conditions)
        ->and($merged?->metadata)->toEqual($existingTarget ? ['keep' => 'target', 'new' => 'source'] : ['new' => 'source'])
        ->and($source->getItems($sourceIdentifier, 'default'))->toBe([]);
})->with([true, false]);

it('rejects stale target metadata before validating the merge payload', function (): void {
    $this->useEngine();

    $suffix = Str::lower(Str::random(12));
    $source = $this->engineStorage();
    $target = $this->engineStorage($this->engineOwner);
    $sourceIdentifier = 'stale-source-' . $suffix;
    $targetIdentifier = 'stale-target-' . $suffix;
    $items = ['item' => ['id' => 'item', 'quantity' => 2, 'price' => 1000]];
    $sourceRevision = $source->putItems($sourceIdentifier, 'default', $items, CartRevision::none());
    $targetRevision = $target->putItems($targetIdentifier, 'default', $items, CartRevision::none());
    $target->putMetadataBatch($targetIdentifier, 'default', ['concurrent' => str_repeat('x', 1024)]);
    config()->set('cart.limits.max_data_size_bytes', 512);

    expect(fn () => $target->commitMergeTransfer(
        $source,
        $sourceIdentifier,
        $targetIdentifier,
        'default',
        $items,
        [],
        ['new' => 'metadata'],
        $sourceRevision,
        $targetRevision,
    ))->toThrow(CartConflictException::class);
    expect($source->getSnapshot($sourceIdentifier, 'default')?->version)->toBe($sourceRevision->version);
});

it('atomically removes item projections and rule metadata and requests one snapshot sync', function (bool $concurrentWrite): void {
    $this->useEngine();
    $events = app(Dispatcher::class);
    $storage = $this->engineStorage();
    $interleaved = new InterleavingStorage($storage);
    $identifier = 'condition-write-' . Str::lower(Str::random(12));
    $factory = new BuiltInRulesFactory;
    $cart = (new Cart($interleaved, $identifier, $events, 'default'))->withRulesFactory($factory);
    $definition = [
        'name' => 'promotion',
        'type' => 'discount',
        'target_definition' => Target::items()->build()->toArray(),
        'value' => '-10%',
        'attributes' => ['condition_id' => 'promotion-id', 'is_global' => true],
    ];

    foreach (range(1, 5) as $index) {
        $cart->add('item-' . $index, 'Item ' . $index, 1000, 1);
    }

    $cart->add('collision', 'Collision', 1000, 1);
    $cart->addItemCondition('collision', CartCondition::fromArray(array_replace($definition, [
        'attributes' => ['condition_id' => 'promotion-id', 'is_global' => false],
    ])));
    $cart->registerDynamicCondition($definition, 'item-quantity-at-least', metadata: ['quantity' => 1]);
    expect($cart->getItems()->get('item-1')?->hasCondition('promotion'))->toBeTrue();
    $cart->registerDynamicCondition(array_replace($definition, ['name' => 'unrelated']), [static fn (): bool => true]);

    $sync = Mockery::mock(CartSnapshotSyncInterface::class);
    $sync->shouldReceive('sync')->once()->with($cart);
    $listener = new SyncCartOnEvent($sync);
    $removedIds = [];
    $events->listen(ItemConditionRemoved::class, function (ItemConditionRemoved $event) use (&$removedIds, $listener, $storage, $identifier): void {
        $removedIds[] = $event->itemId;
        expect($storage->getMetadata($identifier, 'default', 'dynamic_conditions'))->toBe([]);
        $listener->handle($event);
    });
    config()->set('cart.events', true);

    if ($concurrentWrite) {
        $interleaved->interleaveAfter('getSnapshot', fn () => $storage->putMetadata($identifier, 'default', 'concurrent', 'keep'));
    }

    $connection = DB::connection($this->engineConnection);
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $cart->removeDynamicCondition('promotion');
        $updates = array_filter($connection->getQueryLog(), static fn (array $query): bool => str_starts_with($query['query'], 'update "carts"'));
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }

    $restored = (new Cart($storage, $identifier, null, 'default'))->withRulesFactory($factory);
    expect($updates)->toHaveCount($concurrentWrite ? 3 : 1)
        ->and($removedIds)->toEqualCanonicalizing(['item-1', 'item-2', 'item-3', 'item-4', 'item-5'])
        ->and($restored->getMetadata('concurrent'))->toBe($concurrentWrite ? 'keep' : null)
        ->and($storage->getItems($identifier, 'default')['item-1']['conditions'])->not->toHaveKey('unrelated')
        ->and($restored->getDynamicConditions()->isEmpty())->toBeTrue()
        ->and($restored->getItems()->get('item-1')?->hasCondition('promotion'))->toBeFalse()
        ->and($restored->getItems()->get('collision')?->hasCondition('promotion'))->toBeTrue();
})->with([false, true]);
