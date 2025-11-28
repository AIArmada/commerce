<?php

declare(strict_types=1);

namespace AIArmada\Stock\Console;

use AIArmada\Stock\Services\StockReservationService;
use Illuminate\Console\Command;

/**
 * Command to clean up expired stock reservations.
 *
 * Should be scheduled to run periodically (e.g., every 5 minutes).
 */
final class CleanupExpiredReservationsCommand extends Command
{
    protected $signature = 'stock:cleanup-reservations';

    protected $description = 'Clean up expired stock reservations';

    public function handle(StockReservationService $reservationService): int
    {
        $deleted = $reservationService->cleanupExpired();

        $this->info("Cleaned up {$deleted} expired stock reservations.");

        return self::SUCCESS;
    }
}
