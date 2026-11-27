<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\EntryPoints\Console;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Features\Reservations\Queries\ExpiredReservations;
use AIArmada\Inventory\Features\Reservations\Persistence\CheckoutReservationService;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class CleanupExpiredAllocationsCommand extends Command
{
    protected $signature = 'inventory:cleanup-allocations
                            {--dry-run : Show what would be cleaned up without actually cleaning}';

    protected $description = 'Clean up expired inventory allocations';

    public function handle(
        InventoryAllocationService $allocationService,
        CheckoutReservationService $reservationService,
    ): int {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode (no changes will be made)');
        }

        $this->info('Cleaning up expired inventory allocations...');

        $runner = new OwnerBatchRunner(
            InventoryLocation::class,
            ['enabled' => 'inventory.owner.enabled'],
        );

        $runner->run(function () use ($allocationService, $reservationService, $isDryRun): void {
            $this->processScoped($allocationService, $reservationService, $isDryRun);
        });

        return self::SUCCESS;
    }

    private function processScoped(
        InventoryAllocationService $allocationService,
        CheckoutReservationService $reservationService,
        bool $isDryRun,
    ): void {
        if ($isDryRun) {
            $allocationsQuery = InventoryOwnerScope::applyToLocationQuery(
                InventoryAllocation::query()->expired()->whereNull('reservation_group_id')
            );

            $count = $allocationsQuery->count();

            $this->info("Would clean up {$count} expired allocations.");
            $reservationCount = $this->reservationsToCleanupCount();
            $this->info("Would clean up {$reservationCount} expired reservations.");

            return;
        }

        $count = InventoryOwnerScope::isEnabled()
            ? $allocationService->cleanupExpired()
            : $allocationService->cleanupExpiredGlobal();

        $this->info("Cleaned up {$count} expired allocations.");
        $reservationCount = $reservationService->cleanupExpiredReservations();
        $this->info("Cleaned up {$reservationCount} expired reservations.");
    }

    private function reservationsToCleanupCount(): int
    {
        return (new ExpiredReservations)->query(CarbonImmutable::now())->count();
    }
}
