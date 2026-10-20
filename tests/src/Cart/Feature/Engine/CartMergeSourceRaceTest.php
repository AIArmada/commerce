<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

function cartMergeRaceRepeats(): int
{
    return 3;
}

/**
 * @return array{id: string, name: string, price: int, quantity: int, attributes: array<string, mixed>, conditions: array<string, mixed>}
 */
function cartMergeRaceItem(string $id, int $quantity = 1): array
{
    return [
        'id' => $id,
        'name' => 'Item ' . $id,
        'price' => 100,
        'quantity' => $quantity,
        'attributes' => [],
        'conditions' => [],
    ];
}

/**
 * Merge worker + competing source mutation worker pair.
 *
 * The merge worker arms the owner-scoped target snapshot read: the guest read
 * runs through an unarmed global clone, so the hook fires exactly between the
 * in-transaction guest snapshot read and the source consumption. The mutation
 * worker then commits its source write on its own connection and releases the
 * merge, which must lose its revision check, roll every target write back, and
 * recompute from fresh snapshots.
 *
 * @param  callable(): StorageInterface  $targetStorage
 * @return array<string, Closure(string): array<string, mixed>>
 */
function cartMergeSourceRaceWorkers(string $guestIdentifier, string $targetIdentifier, callable $targetStorage, Closure $mutate): array
{
    return [
        'merge' => function (string $workdir) use ($guestIdentifier, $targetIdentifier, $targetStorage): array {
            $interleaved = new InterleavingStorage($targetStorage());

            $interleaved->interleaveAfter('getSnapshot', function () use ($workdir): void {
                file_put_contents($workdir . '/merge-in-read', (string) getmypid());

                $deadline = microtime(true) + 60;

                while (! is_file($workdir . '/source-mutated')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('merge timed out waiting for the concurrent source mutation.');
                    }

                    usleep(1000);
                }
            });

            $action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class), $interleaved);

            return ['merged' => $action->execute($targetIdentifier, 'default', $guestIdentifier)];
        },
        'mutate' => function (string $workdir) use ($mutate): array {
            $deadline = microtime(true) + 60;

            while (! is_file($workdir . '/merge-in-read')) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('mutation timed out waiting for the merge read.');
                }

                usleep(1000);
            }

            $result = $mutate();

            file_put_contents($workdir . '/source-mutated', (string) getmypid());

            return $result;
        },
    ];
}

/**
 * Two merge workers that rendezvous after their target snapshot reads so both
 * consume the same source revision on independent connections.
 *
 * @param  array<string, array{guest: string, target: string, storage: callable}>  $consumers
 * @return array<string, Closure(string): array<string, mixed>>
 */
function cartMergeTwoConsumerWorkers(array $consumers): array
{
    $roles = array_keys($consumers);
    $workers = [];

    foreach ($consumers as $role => $consumer) {
        $workers[$role] = function (string $workdir) use ($role, $consumer, $roles): array {
            $interleaved = new InterleavingStorage(($consumer['storage'])());

            $interleaved->interleaveAfter('getSnapshot', function () use ($workdir, $role, $roles): void {
                file_put_contents($workdir . '/merge-' . $role . '-read', (string) getmypid());

                $deadline = microtime(true) + 60;

                while (true) {
                    $missing = array_filter(
                        $roles,
                        static fn (string $candidate): bool => ! is_file($workdir . '/merge-' . $candidate . '-read')
                    );

                    if ($missing === []) {
                        break;
                    }

                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('merge worker ' . $role . ' timed out waiting for: ' . implode(',', $missing));
                    }

                    usleep(1000);
                }
            });

            $action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class), $interleaved);

            return ['merged' => $action->execute($consumer['target'], 'default', $consumer['guest'])];
        };
    }

    return $workers;
}

/**
 * CART-04 engine race.
 *
 * Genuine multi-process races against PostgreSQL prove the source consumption
 * contract holds on independent connections: a concurrent source mutation
 * between the merge read and the consumption conflicts, rolls every target
 * write back, and is recomputed from fresh snapshots instead of being
 * destroyed by the tombstone. A source row can feed at most one committed
 * merge; the loser copies nothing and consumes nothing.
 */
it('preserves a source item added concurrently during the merge', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-add-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-src-add-target-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'a' => cartMergeRaceItem('a'),
        ], CartRevision::none());

        $results = EngineRace::run(cartMergeSourceRaceWorkers(
            $guestIdentifier,
            $targetIdentifier,
            fn () => $this->engineStorage($owner),
            function () use ($guestIdentifier): array {
                $storage = $this->engineStorage();
                $snapshot = $storage->getSnapshot($guestIdentifier, 'default');

                $items = $snapshot?->items ?? [];
                $items['added'] = cartMergeRaceItem('added', 2);

                $storage->putItems($guestIdentifier, 'default', $items, $snapshot?->revision() ?? CartRevision::none());

                return ['mutated' => true];
            },
        ));

        expect($results['merge']['merged'])->toBeTrue()
            ->and($results['mutate']['mutated'])->toBeTrue();

        $targetItems = $this->engineStorage($owner)->getItems($targetIdentifier, 'default');

        expect(array_keys($targetItems))->toEqualCanonicalizing(['a', 'added'])
            ->and($targetItems['added']['quantity'])->toBe(2)
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $guestRow = DB::connection($this->engineConnection)->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['id', 'version', 'expired_at', 'merged_into_id']);

        expect($guestRow)->not->toBeNull()
            // v1 seed -> v2 concurrent add -> v3 guarded consumption.
            ->and($guestRow->version)->toBe(3)
            ->and($guestRow->expired_at)->not->toBeNull()
            ->and($guestRow->merged_into_id)->not->toBeNull();
    }
});

it('merges the concurrently updated source quantity instead of the stale read', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-upd-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-src-upd-target-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'a' => cartMergeRaceItem('a'),
        ], CartRevision::none());

        $results = EngineRace::run(cartMergeSourceRaceWorkers(
            $guestIdentifier,
            $targetIdentifier,
            fn () => $this->engineStorage($owner),
            function () use ($guestIdentifier): array {
                $storage = $this->engineStorage();
                $snapshot = $storage->getSnapshot($guestIdentifier, 'default');

                $items = $snapshot?->items ?? [];
                $items['a']['quantity'] = 5;

                $storage->putItems($guestIdentifier, 'default', $items, $snapshot?->revision() ?? CartRevision::none());

                return ['mutated' => true];
            },
        ));

        expect($results['merge']['merged'])->toBeTrue();

        $targetItems = $this->engineStorage($owner)->getItems($targetIdentifier, 'default');

        expect($targetItems)->toHaveKey('a')
            ->and($targetItems['a']['quantity'])->toBe(5)
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $guestRow = DB::connection($this->engineConnection)->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['version', 'expired_at']);

        expect($guestRow)->not->toBeNull()
            ->and($guestRow->version)->toBe(3)
            ->and($guestRow->expired_at)->not->toBeNull();
    }
});

it('does not resurrect a source item removed concurrently during the merge', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-rm-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-src-rm-target-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'a' => cartMergeRaceItem('a'),
            'b' => cartMergeRaceItem('b'),
        ], CartRevision::none());

        $results = EngineRace::run(cartMergeSourceRaceWorkers(
            $guestIdentifier,
            $targetIdentifier,
            fn () => $this->engineStorage($owner),
            function () use ($guestIdentifier): array {
                $storage = $this->engineStorage();
                $snapshot = $storage->getSnapshot($guestIdentifier, 'default');

                $items = $snapshot?->items ?? [];
                unset($items['b']);

                $storage->putItems($guestIdentifier, 'default', $items, $snapshot?->revision() ?? CartRevision::none());

                return ['mutated' => true];
            },
        ));

        expect($results['merge']['merged'])->toBeTrue();

        $targetItems = $this->engineStorage($owner)->getItems($targetIdentifier, 'default');

        expect(array_keys($targetItems))->toEqualCanonicalizing(['a'])
            ->and($targetItems)->not->toHaveKey('b')
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $guestRow = DB::connection($this->engineConnection)->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['version', 'expired_at']);

        expect($guestRow)->not->toBeNull()
            ->and($guestRow->version)->toBe(3)
            ->and($guestRow->expired_at)->not->toBeNull();
    }
});

it('merges source metadata and conditions changed concurrently during the merge', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-met-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-src-met-target-' . Str::lower(Str::random(12));

        $seedStorage = $this->engineStorage();
        $seedRevision = $seedStorage->putItems($guestIdentifier, 'default', [
            'a' => cartMergeRaceItem('a'),
        ], CartRevision::none());
        $seedStorage->putConditions($guestIdentifier, 'default', [
            'deal' => ['name' => 'deal', 'value' => '5%'],
        ], $seedRevision);
        $seedStorage->putMetadataBatch($guestIdentifier, 'default', ['note' => 'v1']);

        $results = EngineRace::run(cartMergeSourceRaceWorkers(
            $guestIdentifier,
            $targetIdentifier,
            fn () => $this->engineStorage($owner),
            function () use ($guestIdentifier): array {
                $storage = $this->engineStorage();
                $snapshot = $storage->getSnapshot($guestIdentifier, 'default');

                if ($snapshot === null) {
                    throw new RuntimeException('Concurrent mutation could not read the source snapshot.');
                }

                $storage->putMetadataBatch($guestIdentifier, 'default', ['note' => 'v2']);
                $storage->putConditions($guestIdentifier, 'default', [
                    'deal' => ['name' => 'deal', 'value' => '10%'],
                ], CartRevision::of($snapshot->id, $snapshot->version + 1));

                return ['mutated' => true];
            },
        ));

        expect($results['merge']['merged'])->toBeTrue();

        $targetStorage = $this->engineStorage($owner);

        expect($targetStorage->getAllMetadata($targetIdentifier, 'default')['note'])->toBe('v2')
            ->and($targetStorage->getConditions($targetIdentifier, 'default')['deal']['value'])->toBe('10%')
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $guestRow = DB::connection($this->engineConnection)->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['id', 'version', 'expired_at', 'merged_into_id']);

        expect($guestRow)->not->toBeNull()
            // v1 seed -> v2 conditions -> v3 metadata -> v4 concurrent metadata
            // -> v5 concurrent conditions -> v6 guarded consumption.
            ->and($guestRow->version)->toBe(6)
            ->and($guestRow->expired_at)->not->toBeNull()
            ->and($guestRow->merged_into_id)->not->toBeNull();
    }
});

it('consumes a concurrently recreated source row by its new revision', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-rec-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-src-rec-target-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'old' => cartMergeRaceItem('old'),
        ], CartRevision::none());

        $results = EngineRace::run(cartMergeSourceRaceWorkers(
            $guestIdentifier,
            $targetIdentifier,
            fn () => $this->engineStorage($owner),
            function () use ($guestIdentifier): array {
                $storage = $this->engineStorage();

                $storage->forget($guestIdentifier, 'default');
                $storage->putItems($guestIdentifier, 'default', [
                    'recreated' => cartMergeRaceItem('recreated'),
                ], CartRevision::none());

                return ['mutated' => true];
            },
        ));

        expect($results['merge']['merged'])->toBeTrue();

        $targetItems = $this->engineStorage($owner)->getItems($targetIdentifier, 'default');

        expect(array_keys($targetItems))->toEqualCanonicalizing(['recreated'])
            ->and($targetItems)->not->toHaveKey('old')
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $guestRow = DB::connection($this->engineConnection)->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['id', 'version', 'expired_at', 'merged_into_id']);

        expect($guestRow)->not->toBeNull()
            // v1 recreation -> v2 guarded consumption of the replacement row.
            ->and($guestRow->version)->toBe(2)
            ->and($guestRow->expired_at)->not->toBeNull()
            ->and($guestRow->merged_into_id)->not->toBeNull();
    }
});

it('lets only one of two consumers copy the source into different targets', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-two-guest-' . Str::lower(Str::random(12));
        $targetA = 'carteng-src-two-a-' . Str::lower(Str::random(12));
        $targetB = 'carteng-src-two-b-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'a' => cartMergeRaceItem('a'),
        ], CartRevision::none());

        $results = EngineRace::run(cartMergeTwoConsumerWorkers([
            'a' => ['guest' => $guestIdentifier, 'target' => $targetA, 'storage' => fn () => $this->engineStorage($owner)],
            'b' => ['guest' => $guestIdentifier, 'target' => $targetB, 'storage' => fn () => $this->engineStorage($owner)],
        ]));

        $winners = array_keys(array_filter([
            'a' => $results['a']['merged'],
            'b' => $results['b']['merged'],
        ]));

        expect($winners)->toHaveCount(1);

        $winnerTarget = $winners[0] === 'a' ? $targetA : $targetB;
        $loserTarget = $winners[0] === 'a' ? $targetB : $targetA;
        $targetStorage = $this->engineStorage($owner);

        expect($targetStorage->getItems($winnerTarget, 'default'))->toHaveKey('a')
            // The loser's target write was rolled back: the payload is never copied twice.
            ->and($targetStorage->has($loserTarget, 'default'))->toBeFalse()
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $connection = DB::connection($this->engineConnection);
        $guestRow = $connection->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['id', 'version', 'expired_at', 'merged_into_id']);
        $winnerRow = $connection->table('carts')
            ->where('identifier', $winnerTarget)
            ->where('instance', 'default')
            ->first(['id']);

        expect($guestRow)->not->toBeNull()
            ->and($winnerRow)->not->toBeNull()
            ->and($guestRow->merged_into_id)->toBe($winnerRow->id)
            ->and($guestRow->version)->toBe(2)
            ->and($guestRow->expired_at)->not->toBeNull();
    }
});

it('lets only one of two consumers copy the source into the same target', function (): void {
    $this->useEngine();

    $owner = $this->engineOwner;

    for ($round = 0; $round < cartMergeRaceRepeats(); $round++) {
        $guestIdentifier = 'carteng-src-same-guest-' . Str::lower(Str::random(12));
        $targetIdentifier = 'carteng-src-same-target-' . Str::lower(Str::random(12));

        $this->engineStorage()->putItems($guestIdentifier, 'default', [
            'a' => cartMergeRaceItem('a'),
        ], CartRevision::none());

        $results = EngineRace::run(cartMergeTwoConsumerWorkers([
            'a' => ['guest' => $guestIdentifier, 'target' => $targetIdentifier, 'storage' => fn () => $this->engineStorage($owner)],
            'b' => ['guest' => $guestIdentifier, 'target' => $targetIdentifier, 'storage' => fn () => $this->engineStorage($owner)],
        ]));

        $winners = array_keys(array_filter([
            'a' => $results['a']['merged'],
            'b' => $results['b']['merged'],
        ]));

        expect($winners)->toHaveCount(1);

        $targetStorage = $this->engineStorage($owner);

        expect($targetStorage->getItems($targetIdentifier, 'default'))->toHaveCount(1)
            ->and($targetStorage->getItems($targetIdentifier, 'default')['a']['quantity'])->toBe(1)
            ->and($targetStorage->getVersion($targetIdentifier, 'default'))->toBe(1)
            ->and($this->engineStorage()->getItems($guestIdentifier, 'default'))->toBe([]);

        $connection = DB::connection($this->engineConnection);
        $guestRow = $connection->table('carts')
            ->where('identifier', $guestIdentifier)
            ->where('instance', 'default')
            ->first(['version', 'expired_at', 'merged_into_id']);
        $targetRow = $connection->table('carts')
            ->where('identifier', $targetIdentifier)
            ->where('instance', 'default')
            ->first(['id']);

        expect($guestRow)->not->toBeNull()
            ->and($guestRow->merged_into_id)->toBe($targetRow->id)
            ->and($guestRow->version)->toBe(2)
            ->and($guestRow->expired_at)->not->toBeNull();
    }
});
