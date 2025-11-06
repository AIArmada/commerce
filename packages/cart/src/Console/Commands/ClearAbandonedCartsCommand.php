<?php

declare(strict_types=1);

namespace AIArmada\Cart\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

final class ClearAbandonedCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cart:clear-abandoned 
                          {--days=7 : Number of days after which cart is considered abandoned}
                          {--dry-run : Show what would be deleted without actually deleting}
                          {--batch-size=1000 : Number of records to process in each batch}';

    /**
     * The console command description.
     */
    protected $description = 'Clear abandoned shopping carts older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $table = config('cart.database.table', 'carts');

        $cutoffDate = now()->subDays($days);

        info("Clearing carts abandoned before: {$cutoffDate->format('Y-m-d H:i:s')}");

        if ($dryRun) {
            warning('DRY RUN MODE - No data will be deleted');
        }

        $query = DB::table($table)
            ->where('updated_at', '<', $cutoffDate);

        $totalCount = $query->count();

        if ($totalCount === 0) {
            info('No abandoned carts found.');

            return self::SUCCESS;
        }

        info("Found {$totalCount} abandoned carts to clear.");

        if (! $dryRun) {
            $confirmed = confirm('Are you sure you want to delete these carts?');
            if (! $confirmed) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $deletedCount = 0;

        // Process in batches to avoid memory issues
        progress(
            label: $dryRun ? 'Simulating deletion...' : 'Deleting carts...',
            steps: $query->clone()->pluck('id')->chunk($batchSize),
            callback: function ($chunk) use (&$deletedCount, $dryRun, $table): void {
                if (! $dryRun) {
                    $deleted = DB::table($table)->whereIn('id', $chunk->toArray())->delete();
                    $deletedCount += $deleted;
                } else {
                    $deletedCount += $chunk->count();
                }
            }
        );

        if ($dryRun) {
            info("Would delete {$deletedCount} abandoned carts.");
        } else {
            info("Successfully deleted {$deletedCount} abandoned carts.");
        }

        return self::SUCCESS;
    }
}
