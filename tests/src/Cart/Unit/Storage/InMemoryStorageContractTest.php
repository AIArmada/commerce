<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * CART-02 double contract.
 *
 * The shipped testing double and the repository test double must enforce
 * the same revision semantics as {@see DatabaseStorage} so unit tests can
 * never pass against a laxer contract than production storage.
 */
dataset('in-memory storages', [
    'shipped testing double' => [static fn (): StorageInterface => new InMemoryStorage],
    'repository test double' => [static fn (): StorageInterface => new Tests\Support\Cart\InMemoryStorage],
]);

describe('in-memory storage revision contract', function (): void {
    beforeEach(function (): void {
        config(['cart.events' => false]);
    });

    it('commits a creation and returns the committed revision', function (callable $factory): void {
        $store = $factory();

        $revision = $store->putItems('mem-create', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        expect($revision->cartId)->toBe($store->getId('mem-create', 'default'))
            ->and($revision->version)->toBe(1)
            ->and($store->getVersion('mem-create', 'default'))->toBe(1);
    })->with('in-memory storages');

    it('rejects a stale revision reporting the attempted and current version', function (callable $factory): void {
        $store = $factory();

        $stale = $store->putItems('mem-stale', 'default', ['a' => ['id' => 'a']], CartRevision::none());
        $store->putItems('mem-stale', 'default', ['a' => ['id' => 'a'], 'b' => ['id' => 'b']], $stale);

        try {
            $store->putItems('mem-stale', 'default', ['c' => ['id' => 'c']], $stale);

            $this->fail('A stale revision write must be rejected.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe(1)
                ->and($exception->getCurrentVersion())->toBe(2);
        }

        expect($store->getItems('mem-stale', 'default'))->toHaveKeys(['a', 'b']);
    })->with('in-memory storages');

    it('rejects creation when the cart already exists with attempted version zero', function (callable $factory): void {
        $store = $factory();

        $store->putItems('mem-double-create', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        try {
            $store->putItems('mem-double-create', 'default', ['b' => ['id' => 'b']], CartRevision::none());

            $this->fail('A second creation must be rejected.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe(0)
                ->and($exception->getCurrentVersion())->toBe(1);
        }

        expect($store->getItems('mem-double-create', 'default'))->toHaveKey('a');
    })->with('in-memory storages');

    it('rejects a pre-deletion revision after the cart row was recreated', function (callable $factory): void {
        $store = $factory();

        $old = $store->putItems('mem-recreate', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        $store->forget('mem-recreate', 'default');
        $store->putItems('mem-recreate', 'default', ['b' => ['id' => 'b']], CartRevision::none());

        try {
            $store->putItems('mem-recreate', 'default', ['c' => ['id' => 'c']], $old);

            $this->fail('A revision taken before deletion must never overwrite the replacement row.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe(1)
                ->and($exception->getCurrentVersion())->toBe(1);
        }

        expect($store->getItems('mem-recreate', 'default'))->toHaveKey('b');
    })->with('in-memory storages');

    it('skips the metadata write when the computed value is unchanged and removes on null', function (callable $factory): void {
        $store = $factory();

        $created = $store->putItems('mem-meta', 'default', [], CartRevision::none());
        $store->mutateMetadata('mem-meta', 'default', 'note', static fn (): string => 'same');

        $afterFirst = $store->getVersion('mem-meta', 'default');
        expect($afterFirst)->toBe($created->version + 1);

        $returned = $store->mutateMetadata(
            'mem-meta',
            'default',
            'note',
            static fn (?string $current): ?string => $current
        );

        expect($returned)->toBe('same')
            ->and($store->getVersion('mem-meta', 'default'))->toBe($afterFirst);

        $store->mutateMetadata('mem-meta', 'default', 'note', static fn (): mixed => null);

        expect($store->getMetadata('mem-meta', 'default', 'note'))->toBeNull()
            ->and($store->getAllMetadata('mem-meta', 'default'))->toBeEmpty();
    })->with('in-memory storages');

    it('clears as a blind write that bumps the version and inserts when missing', function (callable $factory): void {
        $store = $factory();

        $store->putItems('mem-blind', 'default', ['a' => ['id' => 'a']], CartRevision::none());
        $store->putMetadata('mem-blind', 'default', 'note', 'value');

        $beforeClear = $store->getVersion('mem-blind', 'default');
        $store->clearMetadata('mem-blind', 'default');

        expect($store->getVersion('mem-blind', 'default'))->toBe($beforeClear + 1)
            ->and($store->getAllMetadata('mem-blind', 'default'))->toBeEmpty();

        $store->clearAll('mem-blind', 'default');

        expect($store->getVersion('mem-blind', 'default'))->toBe($beforeClear + 2)
            ->and($store->getItems('mem-blind', 'default'))->toBeEmpty();

        $store->clearAll('mem-blind-missing', 'default');

        expect($store->getVersion('mem-blind-missing', 'default'))->toBe(1);
    })->with('in-memory storages');

    it('bumps the version on a successful identifier swap and refuses an occupied target', function (callable $factory): void {
        $store = $factory();

        $created = $store->putItems('mem-swap-old', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        expect($store->swapIdentifier('mem-swap-old', 'mem-swap-new', 'default'))->toBeTrue()
            ->and($store->getVersion('mem-swap-new', 'default'))->toBe($created->version + 1)
            ->and($store->has('mem-swap-old', 'default'))->toBeFalse();

        $store->putItems('mem-swap-occupied', 'default', ['b' => ['id' => 'b']], CartRevision::none());

        expect($store->swapIdentifier('mem-swap-new', 'mem-swap-occupied', 'default'))->toBeFalse()
            ->and($store->getItems('mem-swap-occupied', 'default'))->toHaveKey('b')
            ->and($store->getItems('mem-swap-new', 'default'))->toHaveKey('a');
    })->with('in-memory storages');
});

describe('in-memory merge consumption contract', function (): void {
    beforeEach(function (): void {
        config(['cart.events' => false]);
    });

    it('consumes the exact revision, clears content, and expires the source', function (callable $factory): void {
        $store = $factory();

        $created = $store->putItems('mem-consume', 'default', ['a' => ['id' => 'a']], CartRevision::none());
        $store->putMetadataBatch('mem-consume', 'default', ['note' => 'value']);

        $snapshot = $store->getSnapshot('mem-consume', 'default');
        $consumed = $store->consumeForMerge('mem-consume', 'default', $snapshot->revision(), 'target-cart-id');

        expect($consumed->cartId)->toBe($created->cartId)
            ->and($consumed->version)->toBe($snapshot->version + 1)
            ->and($store->getItems('mem-consume', 'default'))->toBeEmpty()
            ->and($store->getConditions('mem-consume', 'default'))->toBeEmpty()
            ->and($store->getAllMetadata('mem-consume', 'default'))->toBeEmpty()
            ->and($store->isExpired('mem-consume', 'default'))->toBeTrue();
    })->with('in-memory storages');

    it('rejects a stale source revision and refuses a second consumption', function (callable $factory): void {
        $store = $factory();

        $store->putItems('mem-consume-twice', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        $stale = $store->getSnapshot('mem-consume-twice', 'default');
        $store->putItems('mem-consume-twice', 'default', ['a' => ['id' => 'a'], 'b' => ['id' => 'b']], $stale->revision());

        try {
            $store->consumeForMerge('mem-consume-twice', 'default', $stale->revision(), 'target-cart-id');

            $this->fail('A stale source revision must be rejected.');
        } catch (CartConflictException $exception) {
            expect($exception->getAttemptedVersion())->toBe($stale->version)
                ->and($exception->getCurrentVersion())->toBe($stale->version + 1);
        }

        expect($store->getItems('mem-consume-twice', 'default'))->toHaveKeys(['a', 'b']);

        $snapshot = $store->getSnapshot('mem-consume-twice', 'default');
        $consumed = $store->consumeForMerge('mem-consume-twice', 'default', $snapshot->revision(), 'target-cart-id');

        try {
            $store->consumeForMerge('mem-consume-twice', 'default', $consumed, 'target-cart-id');

            $this->fail('A consumed source must not be consumed twice.');
        } catch (CartConflictException) {
            // Expected: the row is already tombstoned.
        }

        expect($store->getItems('mem-consume-twice', 'default'))->toBeEmpty();
    })->with('in-memory storages');
});

describe('in-memory guarded cleanup contract', function (): void {
    beforeEach(function (): void {
        config(['cart.events' => false]);
    });

    it('removes the row only while the expected revision is current', function (callable $factory): void {
        $store = $factory();

        $created = $store->putItems('mem-guard-forget', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        expect($store->forgetIfUnchanged('mem-guard-forget', 'default', $created))->toBeTrue()
            ->and($store->has('mem-guard-forget', 'default'))->toBeFalse();
    })->with('in-memory storages');

    it('refuses the guarded removal after a newer write advanced the revision', function (callable $factory): void {
        $store = $factory();

        $created = $store->putItems('mem-guard-forget-stale', 'default', ['a' => ['id' => 'a']], CartRevision::none());
        $store->putItems('mem-guard-forget-stale', 'default', ['a' => ['id' => 'a'], 'b' => ['id' => 'b']], $created);

        expect($store->forgetIfUnchanged('mem-guard-forget-stale', 'default', $created))->toBeFalse()
            ->and($store->getItems('mem-guard-forget-stale', 'default'))->toHaveKeys(['a', 'b']);
    })->with('in-memory storages');

    it('refuses the guarded removal of a replacement row recreated after the revision', function (callable $factory): void {
        $store = $factory();

        $old = $store->putItems('mem-guard-recreate', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        $store->forget('mem-guard-recreate', 'default');
        $replacement = $store->putItems('mem-guard-recreate', 'default', ['b' => ['id' => 'b']], CartRevision::none());

        expect($store->forgetIfUnchanged('mem-guard-recreate', 'default', $old))->toBeFalse()
            ->and($store->getId('mem-guard-recreate', 'default'))->toBe($replacement->cartId)
            ->and($store->getItems('mem-guard-recreate', 'default'))->toHaveKey('b');
    })->with('in-memory storages');

    it('clears the cart and bumps the version only while the expected revision is current', function (callable $factory): void {
        $store = $factory();

        $store->putItems('mem-guard-clear', 'default', ['a' => ['id' => 'a']], CartRevision::none());
        $store->putMetadata('mem-guard-clear', 'default', 'note', 'value');

        $snapshot = $store->getSnapshot('mem-guard-clear', 'default');

        expect($store->clearAllIfUnchanged('mem-guard-clear', 'default', $snapshot->revision()))->toBeTrue()
            ->and($store->getVersion('mem-guard-clear', 'default'))->toBe($snapshot->version + 1)
            ->and($store->getItems('mem-guard-clear', 'default'))->toBeEmpty()
            ->and($store->getAllMetadata('mem-guard-clear', 'default'))->toBeEmpty()
            ->and($store->has('mem-guard-clear', 'default'))->toBeTrue();
    })->with('in-memory storages');

    it('refuses the guarded clear after a newer write advanced the revision', function (callable $factory): void {
        $store = $factory();

        $stale = $store->putItems('mem-guard-clear-stale', 'default', ['a' => ['id' => 'a']], CartRevision::none());
        $store->putItems('mem-guard-clear-stale', 'default', ['a' => ['id' => 'a'], 'b' => ['id' => 'b']], $stale);

        expect($store->clearAllIfUnchanged('mem-guard-clear-stale', 'default', $stale))->toBeFalse()
            ->and($store->getItems('mem-guard-clear-stale', 'default'))->toHaveKeys(['a', 'b']);
    })->with('in-memory storages');

    it('refuses guarded cleanup for a missing row and a creation revision', function (callable $factory): void {
        $store = $factory();

        expect($store->forgetIfUnchanged('mem-guard-missing', 'default', CartRevision::of('missing-id', 1)))->toBeFalse()
            ->and($store->clearAllIfUnchanged('mem-guard-missing', 'default', CartRevision::of('missing-id', 1)))->toBeFalse();

        $store->putItems('mem-guard-creation', 'default', ['a' => ['id' => 'a']], CartRevision::none());

        expect(fn () => $store->forgetIfUnchanged('mem-guard-creation', 'default', CartRevision::none()))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $store->clearAllIfUnchanged('mem-guard-creation', 'default', CartRevision::none()))
            ->toThrow(InvalidArgumentException::class);

        expect($store->getItems('mem-guard-creation', 'default'))->toHaveKey('a');
    })->with('in-memory storages');
});
