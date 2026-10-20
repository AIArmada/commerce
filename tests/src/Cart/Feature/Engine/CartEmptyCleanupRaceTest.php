<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

function cartEmptyCleanupRepeats(): int
{
    return 3;
}

/**
 * @return array{id: string, name: string, price: int, quantity: int, attributes: array<string, mixed>, conditions: array<string, mixed>}
 */
function cartEmptyCleanupItem(string $id, int $price, int $quantity = 1): array
{
    return [
        'id' => $id,
        'name' => 'Item ' . $id,
        'price' => $price,
        'quantity' => $quantity,
        'attributes' => [],
        'conditions' => [],
    ];
}

/**
 * Cart-emptying worker + competing add worker.
 *
 * The cleanup worker commits the removal of the last item and blocks between
 * that commit and automatic empty-cart cleanup until the competing worker has
 * added a new item on its own connection. The guarded cleanup must then see
 * the newer revision and leave the cart (and its new item) intact instead of
 * deleting the newer write.
 *
 * @param  callable(): StorageInterface  $cleanupStorage
 * @param  callable(): StorageInterface  $addStorage
 * @return array<string, Closure(string): array<string, mixed>>
 */
function cartEmptyCleanupWorkers(
    string $identifier,
    string $behavior,
    callable $cleanupStorage,
    callable $addStorage,
): array {
    return [
        'cleanup' => function (string $workdir) use ($identifier, $behavior, $cleanupStorage): array {
            config()->set('cart.empty_cart_behavior', $behavior);

            $interleaved = new InterleavingStorage($cleanupStorage());

            $interleaved->interleaveAfter('putItems', function () use ($workdir): void {
                file_put_contents($workdir . '/empty-committed', (string) getmypid());

                $deadline = microtime(true) + 60;

                while (! is_file($workdir . '/item-added')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('cleanup worker timed out waiting for the concurrent add.');
                    }

                    usleep(1000);
                }
            });

            $returned = (new Cart($interleaved, $identifier))->remove('a');

            return ['removed' => $returned?->id];
        },
        'add' => function (string $workdir) use ($identifier, $addStorage): array {
            $deadline = microtime(true) + 60;

            while (! is_file($workdir . '/empty-committed')) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('add worker timed out waiting for the emptying commit.');
                }

                usleep(1000);
            }

            (new Cart($addStorage(), $identifier))->add('b', 'Item b', 200, 1);

            file_put_contents($workdir . '/item-added', (string) getmypid());

            return ['added' => true];
        },
    ];
}

/**
 * CART-06 engine race.
 *
 * Genuine multi-process races against PostgreSQL prove automatic empty-cart
 * cleanup never erases a newer successful write: the cleanup is guarded by
 * the exact revision the emptying mutation committed, so an item added on an
 * independent connection between the commit and the cleanup first wins and
 * the cart row survives with the new item in both destroy and clear modes.
 */
it('keeps an item added concurrently between the commit and a destroy cleanup', function (): void {
    $this->useEngine();

    for ($round = 0; $round < cartEmptyCleanupRepeats(); $round++) {
        $identifier = 'carteng-cleanup-destroy-' . Str::lower(Str::random(12));

        $seedRevision = $this->engineStorage()->putItems($identifier, 'default', [
            'a' => cartEmptyCleanupItem('a', 100, 1),
        ], CartRevision::none());

        $results = EngineRace::run(cartEmptyCleanupWorkers(
            $identifier,
            'destroy',
            fn () => $this->engineStorage(),
            fn () => $this->engineStorage(),
        ));

        expect($results['cleanup']['removed'])->toBe('a')
            ->and($results['add']['added'])->toBeTrue();

        $storage = $this->engineStorage();
        $items = $storage->getItems($identifier, 'default');

        expect(array_keys($items))->toBe(['b'])
            ->and($items['b']['quantity'])->toBe(1)
            ->and($storage->has($identifier, 'default'))->toBeTrue()
            ->and($storage->getId($identifier, 'default'))->toBe($seedRevision->cartId)
            // v1 seed -> v2 emptying commit -> v3 concurrent add.
            ->and($storage->getVersion($identifier, 'default'))->toBe(3);
    }
});

it('keeps an item added concurrently between the commit and a clear cleanup', function (): void {
    $this->useEngine();

    for ($round = 0; $round < cartEmptyCleanupRepeats(); $round++) {
        $identifier = 'carteng-cleanup-clear-' . Str::lower(Str::random(12));

        $seedRevision = $this->engineStorage()->putItems($identifier, 'default', [
            'a' => cartEmptyCleanupItem('a', 100, 1),
        ], CartRevision::none());

        $results = EngineRace::run(cartEmptyCleanupWorkers(
            $identifier,
            'clear',
            fn () => $this->engineStorage(),
            fn () => $this->engineStorage(),
        ));

        expect($results['cleanup']['removed'])->toBe('a')
            ->and($results['add']['added'])->toBe(true);

        $storage = $this->engineStorage();
        $items = $storage->getItems($identifier, 'default');

        expect(array_keys($items))->toBe(['b'])
            ->and($items['b']['quantity'])->toBe(1)
            ->and($storage->has($identifier, 'default'))->toBeTrue()
            ->and($storage->getId($identifier, 'default'))->toBe($seedRevision->cartId)
            // v1 seed -> v2 emptying commit -> v3 concurrent add.
            ->and($storage->getVersion($identifier, 'default'))->toBe(3);
    }
});
