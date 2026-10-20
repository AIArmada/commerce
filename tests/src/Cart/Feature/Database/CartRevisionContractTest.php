<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * CART-02 storage contract.
 *
 * Every replacing write must commit against the exact revision it was
 * computed from. Stale expectations are rejected with the attempted and
 * current versions instead of silently overwriting a concurrent mutation.
 */
describe('cart revision write contract', function (): void {
    beforeEach(function (): void {
        config(['cart.events' => false]);

        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $this->storage = new DatabaseStorage($connection, 'carts');
        $this->item = fn (string $id): array => [
            'id' => $id,
            'name' => 'Item ' . $id,
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
            'conditions' => [],
        ];
    });

    it('commits a creation and returns the committed revision', function (): void {
        $revision = $this->storage->putItems('rev-create', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        expect($revision->cartId)->toBe($this->storage->getId('rev-create', 'default'))
            ->and($revision->version)->toBe(1)
            ->and($this->storage->getVersion('rev-create', 'default'))->toBe(1);
    });

    it('commits a matching expected revision and bumps the version', function (): void {
        $created = $this->storage->putItems('rev-bump', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        $updated = $this->storage->putItems('rev-bump', 'default', [
            'a' => ($this->item)('a'),
            'b' => ($this->item)('b'),
        ], $created);

        expect($updated->cartId)->toBe($created->cartId)
            ->and($updated->version)->toBe(2)
            ->and($this->storage->getVersion('rev-bump', 'default'))->toBe(2)
            ->and($this->storage->getItems('rev-bump', 'default'))->toHaveKeys(['a', 'b']);
    });

    it('rejects a stale revision reporting the attempted and current version', function (): void {
        $stale = $this->storage->putItems('rev-stale', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());
        $this->storage->putItems('rev-stale', 'default', [
            'a' => ($this->item)('a'),
            'b' => ($this->item)('b'),
        ], $stale);

        try {
            $this->storage->putItems('rev-stale', 'default', [
                'c' => ($this->item)('c'),
            ], $stale);

            $this->fail('A stale revision write must be rejected.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe(1)
                ->and($exception->getCurrentVersion())->toBe(2);
        }

        expect($this->storage->getItems('rev-stale', 'default'))->toHaveKeys(['a', 'b']);
    });

    it('rejects creation when the cart already exists with attempted version zero', function (): void {
        $this->storage->putItems('rev-double-create', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        try {
            $this->storage->putItems('rev-double-create', 'default', [
                'b' => ($this->item)('b'),
            ], CartRevision::none());

            $this->fail('A second creation must be rejected.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe(0)
                ->and($exception->getCurrentVersion())->toBe(1);
        }

        expect($this->storage->getItems('rev-double-create', 'default'))->toHaveKey('a');
    });

    it('rejects a pre-deletion revision after the cart row was recreated', function (): void {
        $old = $this->storage->putItems('rev-recreate', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        $this->storage->forget('rev-recreate', 'default');
        $this->storage->putItems('rev-recreate', 'default', [
            'b' => ($this->item)('b'),
        ], CartRevision::none());

        try {
            $this->storage->putItems('rev-recreate', 'default', [
                'c' => ($this->item)('c'),
            ], $old);

            $this->fail('A revision taken before deletion must never overwrite the replacement row.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe(1)
                ->and($exception->getCurrentVersion())->toBe(1);
        }

        expect($this->storage->getItems('rev-recreate', 'default'))->toHaveKey('b');
    });

    it('commits items and conditions together through one putBoth revision', function (): void {
        $created = $this->storage->putBoth('rev-both', 'default', [
            'a' => ($this->item)('a'),
        ], [
            'deal' => [
                'name' => 'deal',
                'type' => 'discount',
                'target' => 'cart@cart_subtotal/aggregate',
                'value' => '-10',
            ],
        ], CartRevision::none());

        expect($created->version)->toBe(1);

        $updated = $this->storage->putBoth('rev-both', 'default', [
            'a' => ($this->item)('a'),
            'b' => ($this->item)('b'),
        ], [], $created);

        expect($updated->version)->toBe(2)
            ->and($this->storage->getConditions('rev-both', 'default'))->toBeEmpty()
            ->and($this->storage->getItems('rev-both', 'default'))->toHaveKey('b');
    });

    it('skips the metadata write when the computed value is unchanged', function (): void {
        $created = $this->storage->putItems('rev-meta-skip', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        $this->storage->mutateMetadata('rev-meta-skip', 'default', 'note', static fn (): string => 'same');

        $afterFirst = $this->storage->getVersion('rev-meta-skip', 'default');
        expect($afterFirst)->toBe($created->version + 1);

        $returned = $this->storage->mutateMetadata(
            'rev-meta-skip',
            'default',
            'note',
            static fn (?string $current): ?string => $current
        );

        expect($returned)->toBe('same')
            ->and($this->storage->getVersion('rev-meta-skip', 'default'))->toBe($afterFirst);
    });

    it('removes a metadata key when the compute callback returns null', function (): void {
        $this->storage->putItems('rev-meta-null', 'default', [], CartRevision::none());
        $this->storage->putMetadata('rev-meta-null', 'default', 'note', 'value');

        $this->storage->mutateMetadata('rev-meta-null', 'default', 'note', static fn (): mixed => null);

        expect($this->storage->getMetadata('rev-meta-null', 'default', 'note'))->toBeNull()
            ->and($this->storage->getAllMetadata('rev-meta-null', 'default'))->toBeEmpty();
    });

    it('creates the cart row when metadata is written before any items', function (): void {
        $this->storage->putMetadata('rev-meta-first', 'default', 'note', 'value');

        expect($this->storage->getVersion('rev-meta-first', 'default'))->toBe(1)
            ->and($this->storage->getMetadata('rev-meta-first', 'default', 'note'))->toBe('value');
    });

    it('clears metadata and all data as blind writes that bump the version', function (): void {
        $created = $this->storage->putItems('rev-blind', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());
        $this->storage->putMetadataBatch('rev-blind', 'default', ['note' => 'value']);

        $beforeClear = $this->storage->getVersion('rev-blind', 'default');
        $this->storage->clearMetadata('rev-blind', 'default');

        expect($this->storage->getVersion('rev-blind', 'default'))->toBe($beforeClear + 1)
            ->and($this->storage->getAllMetadata('rev-blind', 'default'))->toBeEmpty();

        $this->storage->clearAll('rev-blind', 'default');

        expect($this->storage->getVersion('rev-blind', 'default'))->toBe($beforeClear + 2)
            ->and($this->storage->getItems('rev-blind', 'default'))->toBeEmpty();

        // A missing cart is inserted in its cleared state.
        $this->storage->clearAll('rev-blind-missing', 'default');

        expect($this->storage->getVersion('rev-blind-missing', 'default'))->toBe(1);
    });

    it('bumps the version on a successful identifier swap and refuses an occupied target', function (): void {
        $created = $this->storage->putItems('rev-swap-old', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        expect($this->storage->swapIdentifier('rev-swap-old', 'rev-swap-new', 'default'))->toBeTrue()
            ->and($this->storage->getVersion('rev-swap-new', 'default'))->toBe($created->version + 1)
            ->and($this->storage->has('rev-swap-old', 'default'))->toBeFalse();

        $this->storage->putItems('rev-swap-occupied', 'default', [
            'b' => ($this->item)('b'),
        ], CartRevision::none());

        expect($this->storage->swapIdentifier('rev-swap-new', 'rev-swap-occupied', 'default'))->toBeFalse()
            ->and($this->storage->getItems('rev-swap-occupied', 'default'))->toHaveKey('b')
            ->and($this->storage->getItems('rev-swap-new', 'default'))->toHaveKey('a');
    });
});

describe('cart merge consumption contract', function (): void {
    beforeEach(function (): void {
        config(['cart.events' => false]);

        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $this->storage = new DatabaseStorage($connection, 'carts');
        $this->item = fn (string $id): array => [
            'id' => $id,
            'name' => 'Item ' . $id,
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
            'conditions' => [],
        ];
    });

    it('consumes the exact source revision and records attribution', function (): void {
        $created = $this->storage->putItems('rev-consume', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());
        $this->storage->putMetadataBatch('rev-consume', 'default', ['note' => 'value']);

        $target = $this->storage->putItems('rev-consume-target', 'default', [
            't' => ($this->item)('t'),
        ], CartRevision::none());

        $snapshot = $this->storage->getSnapshot('rev-consume', 'default');
        $consumed = $this->storage->consumeForMerge('rev-consume', 'default', $snapshot->revision(), $target->cartId);

        expect($consumed->cartId)->toBe($created->cartId)
            ->and($consumed->version)->toBe($snapshot->version + 1)
            ->and($this->storage->getItems('rev-consume', 'default'))->toBeEmpty()
            ->and($this->storage->getConditions('rev-consume', 'default'))->toBeEmpty()
            ->and($this->storage->getAllMetadata('rev-consume', 'default'))->toBeEmpty();

        $row = DB::table('carts')->where('id', $created->cartId)->first();

        expect($row->merged_into_id)->toBe($target->cartId)
            ->and($row->expired_at)->not->toBeNull();
    });

    it('rejects consuming a stale source revision without touching content', function (): void {
        $created = $this->storage->putItems('rev-consume-stale', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        $stale = $this->storage->getSnapshot('rev-consume-stale', 'default');
        $this->storage->putItems('rev-consume-stale', 'default', [
            'a' => ($this->item)('a'),
            'b' => ($this->item)('b'),
        ], $stale->revision());

        $target = $this->storage->putItems('rev-consume-stale-target', 'default', [
            't' => ($this->item)('t'),
        ], CartRevision::none());

        try {
            $this->storage->consumeForMerge('rev-consume-stale', 'default', $stale->revision(), $target->cartId);
            $this->fail('A stale source revision must be rejected.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe($stale->version)
                ->and($exception->getCurrentVersion())->toBe($stale->version + 1);
        }

        expect($this->storage->getItems('rev-consume-stale', 'default'))->toHaveKeys(['a', 'b']);

        $row = DB::table('carts')->where('id', $created->cartId)->first();

        expect($row->merged_into_id)->toBeNull()
            ->and($row->expired_at)->toBeNull();
    });

    it('refuses a creation revision and a second consumption', function (): void {
        $this->storage->putItems('rev-consume-twice', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        $target = $this->storage->putItems('rev-consume-twice-target', 'default', [
            't' => ($this->item)('t'),
        ], CartRevision::none());

        expect(fn () => $this->storage->consumeForMerge('rev-consume-twice', 'default', CartRevision::none(), $target->cartId))
            ->toThrow(InvalidArgumentException::class);

        $snapshot = $this->storage->getSnapshot('rev-consume-twice', 'default');
        $consumed = $this->storage->consumeForMerge('rev-consume-twice', 'default', $snapshot->revision(), $target->cartId);

        expect(fn () => $this->storage->consumeForMerge('rev-consume-twice', 'default', $consumed, $target->cartId))
            ->toThrow(CartConflictException::class);
    });
});

describe('cart guarded cleanup contract', function (): void {
    beforeEach(function (): void {
        config(['cart.events' => false]);

        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $this->storage = new DatabaseStorage($connection, 'carts');
        $this->item = fn (string $id): array => [
            'id' => $id,
            'name' => 'Item ' . $id,
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
            'conditions' => [],
        ];
    });

    it('removes the row only while the expected revision is current', function (): void {
        $created = $this->storage->putItems('guard-forget', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        expect($this->storage->forgetIfUnchanged('guard-forget', 'default', $created))->toBeTrue()
            ->and($this->storage->has('guard-forget', 'default'))->toBeFalse();
    });

    it('refuses the guarded removal after a newer write advanced the revision', function (): void {
        $created = $this->storage->putItems('guard-forget-stale', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());
        $this->storage->putItems('guard-forget-stale', 'default', [
            'a' => ($this->item)('a'),
            'b' => ($this->item)('b'),
        ], $created);

        expect($this->storage->forgetIfUnchanged('guard-forget-stale', 'default', $created))->toBeFalse()
            ->and($this->storage->getItems('guard-forget-stale', 'default'))->toHaveKeys(['a', 'b']);
    });

    it('refuses the guarded removal of a replacement row recreated after the revision', function (): void {
        $old = $this->storage->putItems('guard-forget-recreate', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        $this->storage->forget('guard-forget-recreate', 'default');
        $replacement = $this->storage->putItems('guard-forget-recreate', 'default', [
            'b' => ($this->item)('b'),
        ], CartRevision::none());

        expect($this->storage->forgetIfUnchanged('guard-forget-recreate', 'default', $old))->toBeFalse()
            ->and($this->storage->getId('guard-forget-recreate', 'default'))->toBe($replacement->cartId)
            ->and($this->storage->getItems('guard-forget-recreate', 'default'))->toHaveKey('b');
    });

    it('clears the cart and bumps the version only while the expected revision is current', function (): void {
        $this->storage->putItems('guard-clear', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());
        $this->storage->putMetadata('guard-clear', 'default', 'note', 'value');

        $snapshot = $this->storage->getSnapshot('guard-clear', 'default');

        expect($this->storage->clearAllIfUnchanged('guard-clear', 'default', $snapshot->revision()))->toBeTrue()
            ->and($this->storage->getVersion('guard-clear', 'default'))->toBe($snapshot->version + 1)
            ->and($this->storage->getItems('guard-clear', 'default'))->toBeEmpty()
            ->and($this->storage->getAllMetadata('guard-clear', 'default'))->toBeEmpty()
            ->and($this->storage->has('guard-clear', 'default'))->toBeTrue();
    });

    it('refuses the guarded clear after a newer write advanced the revision', function (): void {
        $stale = $this->storage->putItems('guard-clear-stale', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());
        $this->storage->putItems('guard-clear-stale', 'default', [
            'a' => ($this->item)('a'),
            'b' => ($this->item)('b'),
        ], $stale);

        expect($this->storage->clearAllIfUnchanged('guard-clear-stale', 'default', $stale))->toBeFalse()
            ->and($this->storage->getItems('guard-clear-stale', 'default'))->toHaveKeys(['a', 'b']);
    });

    it('refuses guarded cleanup for a missing row and a creation revision', function (): void {
        expect($this->storage->forgetIfUnchanged('guard-missing', 'default', CartRevision::of('missing-id', 1)))->toBeFalse()
            ->and($this->storage->clearAllIfUnchanged('guard-missing', 'default', CartRevision::of('missing-id', 1)))->toBeFalse();

        $this->storage->putItems('guard-creation', 'default', [
            'a' => ($this->item)('a'),
        ], CartRevision::none());

        expect(fn () => $this->storage->forgetIfUnchanged('guard-creation', 'default', CartRevision::none()))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $this->storage->clearAllIfUnchanged('guard-creation', 'default', CartRevision::none()))
            ->toThrow(InvalidArgumentException::class);

        expect($this->storage->getItems('guard-creation', 'default'))->toHaveKey('a');
    });
});
