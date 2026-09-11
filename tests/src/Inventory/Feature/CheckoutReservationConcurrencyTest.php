<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * @return list<array{success: bool, result?: array<string, mixed>, exception?: string, message?: string}>
 */
function inventoryConcurrencyRunParallelAttempts(string $databasePath, Closure $attempt): array
{
    $barrierPath = inventoryConcurrencyTemporaryPath('inv-barrier-');
    $readyPaths = [
        inventoryConcurrencyTemporaryPath('inv-ready-'),
        inventoryConcurrencyTemporaryPath('inv-ready-'),
    ];
    $resultPaths = [
        inventoryConcurrencyTemporaryPath('inv-result-'),
        inventoryConcurrencyTemporaryPath('inv-result-'),
    ];
    $processIds = [];

    try {
        foreach ([0, 1] as $attemptNumber) {
            $processId = pcntl_fork();

            if ($processId === -1) {
                throw new RuntimeException('Unable to fork an inventory concurrency attempt.');
            }

            if ($processId === 0) {
                file_put_contents($readyPaths[$attemptNumber], 'ready', LOCK_EX);

                try {
                    config()->set('database.connections.testing.database', $databasePath);
                    DB::purge('testing');
                    DB::connection('testing')->statement('PRAGMA busy_timeout = 10000');

                    while (! is_file($barrierPath)) {
                        usleep(1000);
                    }

                    $result = [
                        'success' => true,
                        'result' => $attempt($attemptNumber),
                    ];
                } catch (Throwable $exception) {
                    $result = [
                        'success' => false,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ];
                }

                file_put_contents($resultPaths[$attemptNumber], json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);

                exit(0);
            }

            $processIds[] = $processId;
        }

        $deadline = microtime(true) + 10;

        while (! is_file($readyPaths[0]) || ! is_file($readyPaths[1])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Inventory concurrency attempts did not reach the barrier.');
            }

            usleep(1000);
        }

        file_put_contents($barrierPath, 'go', LOCK_EX);

        foreach ($processIds as $processId) {
            pcntl_waitpid($processId, $status);
        }

        $results = [];

        foreach ($resultPaths as $resultPath) {
            $json = file_get_contents($resultPath);

            if ($json === false) {
                throw new RuntimeException('An inventory concurrency attempt did not write a result.');
            }

            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($result)) {
                throw new RuntimeException('An inventory concurrency result was not an array.');
            }

            $results[] = $result;
        }

        return $results;
    } finally {
        foreach ($processIds as $processId) {
            $status = 0;
            $state = pcntl_waitpid($processId, $status, WNOHANG);

            if ($state === 0 && function_exists('posix_kill')) {
                posix_kill($processId, SIGTERM);
                pcntl_waitpid($processId, $status);
            }
        }

        foreach ([$barrierPath, ...$readyPaths, ...$resultPaths] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

function inventoryConcurrencyTemporaryPath(string $prefix): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix);

    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary inventory concurrency path.');
    }

    unlink($path);

    return $path;
}

function inventoryConcurrencyCreateDatabaseCopy(): string
{
    $databasePath = inventoryConcurrencyTemporaryPath('inv-database-');
    $connection = DB::connection('testing');

    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $escapedPath = str_replace("'", "''", $databasePath);
    $connection->statement("VACUUM INTO '{$escapedPath}'");

    return $databasePath;
}

function inventoryConcurrencyUseDatabase(string $databasePath): void
{
    config()->set('database.connections.testing.database', $databasePath);
    DB::purge('testing');
    DB::connection('testing')->statement('PRAGMA journal_mode = WAL');
    DB::connection('testing')->statement('PRAGMA busy_timeout = 10000');
}

function inventoryConcurrencyRestoreDatabase(string $databasePath, string $originalDatabase): void
{
    config()->set('database.connections.testing.database', $originalDatabase);
    DB::purge('testing');

    if (is_file($databasePath)) {
        unlink($databasePath);
    }
}

it('concurrently creates exactly one level for an inventoryable and location', function (): void {
    config()->set('inventory.owner.enabled', false);
    config()->set('inventory.models.product', InventoryItem::class);

    $item = InventoryItem::create(['name' => 'Concurrent Level Item']);
    $location = InventoryLocation::factory()->create([
        'name' => 'Concurrent Level Location',
        'code' => 'LOC-CONCURRENT-LEVEL',
    ]);
    $originalDatabase = config('database.connections.testing.database');

    if (! is_string($originalDatabase)) {
        throw new RuntimeException('The testing database path must be a string.');
    }

    $databasePath = inventoryConcurrencyCreateDatabaseCopy();

    try {
        inventoryConcurrencyUseDatabase($databasePath);

        $results = inventoryConcurrencyRunParallelAttempts($databasePath, function () use ($item, $location): array {
            $level = app(InventoryService::class)->getOrCreateLevel(
                InventoryItem::query()->findOrFail($item->getKey()),
                (string) $location->getKey(),
            );

            return [
                'level_id' => (string) $level->getKey(),
                'created' => $level->wasRecentlyCreated,
            ];
        });

        expect($results)->toHaveCount(2)
            ->and(array_filter($results, fn (array $result): bool => ! $result['success']))->toBeEmpty();

        $levelIds = array_map(
            fn (array $result): ?string => $result['result']['level_id'] ?? null,
            $results,
        );
        $createdLevels = array_filter(
            $results,
            fn (array $result): bool => $result['result']['created'] === true,
        );
        $recoveredLevels = array_filter(
            $results,
            fn (array $result): bool => $result['result']['created'] === false,
        );

        expect(array_unique($levelIds))->toHaveCount(1)
            ->and($createdLevels)->toHaveCount(1)
            ->and($recoveredLevels)->toHaveCount(1)
            ->and(InventoryLevel::query()
                ->where('inventoryable_type', $item->getMorphClass())
                ->where('inventoryable_id', $item->getKey())
                ->where('location_id', $location->getKey())
                ->count())->toBe(1);
    } finally {
        inventoryConcurrencyRestoreDatabase($databasePath, $originalDatabase);
    }
});

it('concurrently reserves stock with exactly one winner and no orphaned reservation', function (): void {
    config()->set('inventory.owner.enabled', false);
    config()->set('inventory.models.product', InventoryItem::class);

    $item = InventoryItem::create(['name' => 'Concurrent Reservation Item']);
    $location = InventoryLocation::factory()->create([
        'name' => 'Concurrent Reservation Location',
        'code' => 'LOC-CONCURRENT-RESERVE',
    ]);

    app(InventoryService::class)->receive($item, (string) $location->getKey(), 10);

    $originalDatabase = config('database.connections.testing.database');

    if (! is_string($originalDatabase)) {
        throw new RuntimeException('The testing database path must be a string.');
    }

    $databasePath = inventoryConcurrencyCreateDatabaseCopy();

    try {
        inventoryConcurrencyUseDatabase($databasePath);

        $results = inventoryConcurrencyRunParallelAttempts($databasePath, function (int $attempt) use ($item): array {
            $reference = 'concurrent-reservation-' . $attempt;
            $outcome = app(CheckoutReservationServiceInterface::class)->reserve(
                reference: $reference,
                lines: [new ReservationLine(productId: (string) $item->getKey(), quantity: 6)],
                ttlSeconds: 900,
            );

            return [
                'reference' => $reference,
                'state' => $outcome->state,
            ];
        });

        $winners = array_values(array_filter(
            $results,
            fn (array $result): bool => $result['success'] === true,
        ));
        $losers = array_values(array_filter(
            $results,
            fn (array $result): bool => $result['success'] === false,
        ));

        expect($winners)->toHaveCount(1)
            ->and($losers)->toHaveCount(1)
            ->and($losers[0]['exception'])->toBe(InsufficientInventoryException::class);

        $winnerReference = $winners[0]['result']['reference'];
        $loserReference = $winnerReference === 'concurrent-reservation-0'
            ? 'concurrent-reservation-1'
            : 'concurrent-reservation-0';
        $reservation = InventoryReservation::query()
            ->where('reference', $winnerReference)
            ->firstOrFail();

        expect($winners[0]['result']['state'])->toBe('reserved')
            ->and(InventoryReservation::query()->count())->toBe(1)
            ->and(InventoryReservation::query()->whereDoesntHave('allocations')->count())->toBe(0)
            ->and($reservation->allocations()->sum('quantity'))->toBe(6)
            ->and(InventoryReservation::query()->where('reference', $loserReference)->exists())->toBeFalse()
            ->and(InventoryAllocation::query()->where('cart_id', $loserReference)->exists())->toBeFalse()
            ->and(InventoryLevel::query()->firstOrFail()->quantity_reserved)->toBe(6);
    } finally {
        inventoryConcurrencyRestoreDatabase($databasePath, $originalDatabase);
    }
});
