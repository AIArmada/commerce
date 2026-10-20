<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

function cartEngineRepeats(): int
{
    return 5;
}

/**
 * @return array{id: string, name: string, price: int, quantity: int, attributes: array<string, mixed>, conditions: array<string, mixed>}
 */
function cartEngineItem(string $id, int $price, int $quantity = 1): array
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
 * CART-02 engine race.
 *
 * Genuine multi-process races against PostgreSQL prove the revision contract
 * holds when writers overlap on separate connections: every concurrent
 * successful mutation is preserved, and the guest-to-user merge retries
 * against the fresh snapshot instead of overwriting the target cart write.
 */
it('preserves both distinct items created by concurrent writers on an empty cart', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartEngineRepeats(); $round++) {
        $identifier = 'carteng-distinct-' . Str::lower(Str::random(12));

        EngineRace::run([
            'a' => function () use ($identifier, $owner): array {
                (new Cart($this->engineStorage($owner), $identifier))->add('a', 'Item a', 100, 1);

                return ['committed' => true];
            },
            'b' => function () use ($identifier, $owner): array {
                (new Cart($this->engineStorage($owner), $identifier))->add('b', 'Item b', 200, 1);

                return ['committed' => true];
            },
        ]);

        $storage = $this->engineStorage($owner);
        $items = $storage->getItems($identifier, 'default');

        expect(array_keys($items))->toEqualCanonicalizing(['a', 'b'])
            ->and($items['a']['quantity'])->toBe(1)
            ->and($items['b']['quantity'])->toBe(1)
            ->and($storage->getVersion($identifier, 'default'))->toBe(2);
    }
});

it('keeps both concurrent increments of the same item on an empty cart', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartEngineRepeats(); $round++) {
        $identifier = 'carteng-same-item-' . Str::lower(Str::random(12));

        EngineRace::run([
            'a' => function () use ($identifier, $owner): array {
                (new Cart($this->engineStorage($owner), $identifier))->add('seed', 'Item seed', 500, 1);

                return ['committed' => true];
            },
            'b' => function () use ($identifier, $owner): array {
                (new Cart($this->engineStorage($owner), $identifier))->add('seed', 'Item seed', 500, 1);

                return ['committed' => true];
            },
        ]);

        $storage = $this->engineStorage($owner);
        $items = $storage->getItems($identifier, 'default');

        expect($items)->toHaveKey('seed')
            ->and($items['seed']['quantity'])->toBe(2)
            ->and($storage->getVersion($identifier, 'default'))->toBe(2);
    }
});

it('never drops a concurrent target write during guest cart migration', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartEngineRepeats(); $round++) {
        $guestIdentifier = 'carteng-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-target-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'guest' => cartEngineItem('guest', 300, 1),
        ], CartRevision::none());

        $this->engineStorage($owner)->putItems($targetIdentifier, 'default', [
            'seed' => cartEngineItem('seed', 500, 1),
        ], CartRevision::none());

        // Deterministic overlap: the merge blocks right after it reads the
        // target snapshot (armed on the owner-scoped storage; the guest read
        // goes through an unarmed global clone) until the competing add has
        // committed on its own connection. The merge then must lose its
        // revision check and retry against fresh content rather than
        // overwriting the add.
        $results = EngineRace::run([
            'merge' => function (string $workdir) use ($guestIdentifier, $targetIdentifier, $owner): array {
                $interleaved = new InterleavingStorage($this->engineStorage($owner));

                $interleaved->interleaveAfter('getSnapshot', function () use ($workdir): void {
                    file_put_contents($workdir . '/merge-target-read', (string) getmypid());

                    $deadline = microtime(true) + 60;

                    while (! is_file($workdir . '/add-committed')) {
                        if (microtime(true) > $deadline) {
                            throw new RuntimeException('merge timed out waiting for the concurrent add to commit.');
                        }

                        usleep(1000);
                    }
                });

                $action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class), $interleaved);

                return ['merged' => $action->execute($targetIdentifier, 'default', $guestIdentifier)];
            },
            'add' => function (string $workdir) use ($targetIdentifier, $owner): array {
                $deadline = microtime(true) + 60;

                while (! is_file($workdir . '/merge-target-read')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('add timed out waiting for the merge target read.');
                    }

                    usleep(1000);
                }

                (new Cart($this->engineStorage($owner), $targetIdentifier))->add('live', 'Item live', 200, 1);

                file_put_contents($workdir . '/add-committed', (string) getmypid());

                return ['committed' => true];
            },
        ]);

        expect($results['merge']['merged'])->toBeTrue()
            ->and($results['add']['committed'])->toBeTrue();

        $targetItems = $this->engineStorage($owner)->getItems($targetIdentifier, 'default');

        expect(array_keys($targetItems))->toEqualCanonicalizing(['seed', 'live', 'guest'])
            ->and($targetItems['seed']['quantity'])->toBe(1)
            ->and($targetItems['live']['quantity'])->toBe(1)
            ->and($targetItems['guest']['quantity'])->toBe(1);

        expect($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $connection = DB::connection($this->engineConnection);
        $guestRow = $connection->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['id', 'expired_at', 'merged_into_id', 'version']);
        $targetRow = $connection->table('carts')
            ->where('identifier', $targetIdentifier)
            ->where('instance', 'default')
            ->first(['id', 'version']);

        expect($guestRow)->not->toBeNull()
            ->and($targetRow)->not->toBeNull()
            ->and($guestRow->expired_at)->not->toBeNull()
            ->and($guestRow->merged_into_id)->toBe($targetRow->id)
            // v1 target seed -> v2 competing add -> v3 retried merge commit.
            ->and($targetRow->version)->toBe(3);
    }
});
