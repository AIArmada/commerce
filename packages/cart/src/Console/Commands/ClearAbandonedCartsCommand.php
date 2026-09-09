<?php

declare(strict_types=1);

namespace AIArmada\Cart\Console\Commands;

use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Support\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\progress;

final class ClearAbandonedCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cart:clear-abandoned 
                          {--days=7 : Number of days after which cart is considered abandoned}
                          {--mark-only : Mark abandoned cart snapshots without deleting live carts}
                          {--minutes= : Minutes of inactivity before marking a snapshot as abandoned}
                          {--expired : Only delete carts that have passed their expires_at timestamp}
                          {--dry-run : Show what would be deleted without actually deleting}
                          {--delete : Physically delete carts that already have abandoned_at set (skips detection)}
                          {--all-owners : Process every owner when no owner context is available}
                          {--confirm-all-owners : Confirm multi-owner snapshot mutation}
                          {--max-affected=1000 : Maximum snapshots that may be marked in one run}
                          {--force-threshold : Allow snapshot updates above max-affected threshold}
                          {--strict-owner-tuples : Abort when encountering malformed owner tuples}
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
        if ((bool) $this->option('mark-only')) {
            return $this->markSnapshotsOnly();
        }

        $days = (int) $this->option('days');
        $useExpired = $this->option('expired');
        $dryRun = $this->option('dry-run');
        $deleteMode = (bool) $this->option('delete');
        $allOwners = (bool) $this->option('all-owners');
        $strictOwnerTuples = (bool) $this->option('strict-owner-tuples');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $table = config('cart.database.table', 'carts');
        $now = CarbonImmutable::now();

        if ($deleteMode) {
            $this->info('Delete mode: Physically deleting carts with abandoned_at set');
        } elseif ($useExpired) {
            $this->info('Clearing expired carts (using expires_at column)');
        } else {
            $cutoffDate = $now->subDays($days);
            $this->info("Clearing carts abandoned before: {$cutoffDate->format('Y-m-d H:i:s')}");
        }

        if ((bool) config('cart.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                if (OwnerContext::isExplicitGlobal()) {
                    return $this->handleForOwner(
                        table: $table,
                        useExpired: $useExpired,
                        days: $days,
                        now: $now,
                        dryRun: (bool) $dryRun,
                        batchSize: $batchSize,
                        deleteMode: $deleteMode,
                        ownerType: null,
                        ownerId: null,
                    );
                }

                if (! $allOwners) {
                    $this->error('Owner scoping is enabled but no owner context was resolved. Pass --all-owners to process every owner.');

                    return self::FAILURE;
                }

                return $this->handleAllOwners(
                    table: $table,
                    useExpired: $useExpired,
                    days: $days,
                    now: $now,
                    dryRun: (bool) $dryRun,
                    batchSize: $batchSize,
                    strictOwnerTuples: $strictOwnerTuples,
                    deleteMode: $deleteMode,
                );
            }

            return $this->handleForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: (bool) $dryRun,
                batchSize: $batchSize,
                deleteMode: $deleteMode,
                ownerType: $owner->getMorphClass(),
                ownerId: $owner->getKey(),
            );
        }

        return $this->handleForOwner(
            table: $table,
            useExpired: $useExpired,
            days: $days,
            now: $now,
            dryRun: (bool) $dryRun,
            batchSize: $batchSize,
            deleteMode: $deleteMode,
            ownerType: null,
            ownerId: null,
        );
    }

    private function markSnapshotsOnly(): int
    {
        if (! config('cart.snapshots.abandonment_tracking', true)) {
            $this->warn('Snapshot abandonment tracking is disabled via cart.snapshots.abandonment_tracking.');

            return self::SUCCESS;
        }

        $minutesOption = $this->option('minutes');
        $minutes = is_numeric($minutesOption)
            ? (int) $minutesOption
            : (int) config('cart.snapshots.abandonment_detection_minutes', 30);
        $minutes = max(0, $minutes);
        $dryRun = (bool) $this->option('dry-run');
        $allOwners = (bool) $this->option('all-owners');
        $confirmAllOwners = (bool) $this->option('confirm-all-owners');
        $maxAffected = max(1, (int) $this->option('max-affected'));
        $forceThreshold = (bool) $this->option('force-threshold');
        $strictOwnerTuples = (bool) $this->option('strict-owner-tuples');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No snapshot rows will be updated');
        }

        if ($allOwners && ! $dryRun && ! $confirmAllOwners) {
            $this->error('Multi-owner snapshot mutation requires --confirm-all-owners. Run once with --dry-run first.');

            return self::FAILURE;
        }

        if (
            CartSnapshot::ownerScopingEnabled()
            && OwnerContext::resolve() === null
            && ! OwnerContext::isExplicitGlobal()
        ) {
            if (! $allOwners) {
                $this->error('Owner scoping is enabled but no owner context was resolved. Pass --all-owners to process every owner.');

                return self::FAILURE;
            }

            return $this->markSnapshotsForAllOwners(
                minutes: $minutes,
                dryRun: $dryRun,
                maxAffected: $maxAffected,
                forceThreshold: $forceThreshold,
                strictOwnerTuples: $strictOwnerTuples,
            );
        }

        $candidateCount = $this->countAbandonedSnapshots($minutes);

        if (! $dryRun && ! $forceThreshold && $candidateCount > $maxAffected) {
            $this->error("Refusing to mark {$candidateCount} snapshots: exceeds --max-affected={$maxAffected}. Use --force-threshold to proceed.");

            return self::FAILURE;
        }

        $marked = $this->processAbandonedSnapshots($minutes, $dryRun);

        $this->info("Marked {$marked} snapshot(s) as abandoned.");

        return self::SUCCESS;
    }

    private function markSnapshotsForAllOwners(
        int $minutes,
        bool $dryRun,
        int $maxAffected,
        bool $forceThreshold,
        bool $strictOwnerTuples,
    ): int {
        $columns = OwnerTupleColumns::forModelClass(CartSnapshot::class);
        $owners = OwnerContext::withOwner(null, fn () => CartSnapshot::query()
            ->withoutOwnerScope()
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get());

        $ownerBatches = [];
        $totalCandidates = 0;

        foreach ($owners as $row) {
            $parsed = OwnerTupleParser::fromRow(
                row: $row,
                columns: $columns,
                allowMalformed: true,
            );

            if ($parsed->isUnresolved()) {
                $message = sprintf(
                    'Malformed owner tuple encountered (owner_type: %s, owner_id: %s).',
                    $row->{$columns->ownerTypeColumn} ?? 'null',
                    $row->{$columns->ownerIdColumn} === null ? 'null' : (string) $row->{$columns->ownerIdColumn},
                );

                if ($strictOwnerTuples) {
                    $this->error($message);

                    return self::FAILURE;
                }

                $this->warn("Skipping {$message}");

                continue;
            }

            $owner = $parsed->toOwnerModel();
            $count = (int) OwnerContext::withOwner($owner, fn (): int => $this->countAbandonedSnapshots($minutes));
            $ownerBatches[] = ['owner' => $owner, 'count' => $count];
            $totalCandidates += $count;
        }

        if ($owners->isEmpty()) {
            $count = (int) OwnerContext::withOwner(null, fn (): int => $this->countAbandonedSnapshots($minutes));
            $ownerBatches[] = ['owner' => null, 'count' => $count];
            $totalCandidates += $count;
        }

        if (! $dryRun && ! $forceThreshold && $totalCandidates > $maxAffected) {
            $this->error("Refusing to mark {$totalCandidates} snapshots: exceeds --max-affected={$maxAffected}. Use --force-threshold to proceed.");

            return self::FAILURE;
        }

        $totalMarked = 0;

        foreach ($ownerBatches as $batch) {
            $totalMarked += (int) OwnerContext::withOwner(
                $batch['owner'],
                fn (): int => $this->processAbandonedSnapshots($minutes, $dryRun),
            );
        }

        $this->info("Marked {$totalMarked} snapshot(s) as abandoned across owners.");

        return self::SUCCESS;
    }

    private function countAbandonedSnapshots(int $minutes): int
    {
        return $this->abandonedSnapshotsQuery($minutes)->count();
    }

    private function processAbandonedSnapshots(int $minutes, bool $dryRun): int
    {
        $snapshots = $this->abandonedSnapshotsQuery($minutes)->get();

        foreach ($snapshots as $snapshot) {
            if (! $dryRun) {
                $snapshot->markAsAbandoned();
            }
        }

        return $snapshots->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<CartSnapshot>
     */
    private function abandonedSnapshotsQuery(int $minutes): \Illuminate\Database\Eloquent\Builder
    {
        $cutoff = CarbonImmutable::now()->subMinutes($minutes);

        return CartSnapshot::query()->forOwner()
            ->where('items_count', '>', 0)
            ->whereNotNull('checkout_started_at')
            ->whereNull('checkout_abandoned_at')
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_activity_at', '<', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('last_activity_at')
                            ->where('checkout_started_at', '<', $cutoff);
                    });
            });
    }

    private function handleAllOwners(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        bool $dryRun,
        int $batchSize,
        bool $strictOwnerTuples,
        bool $deleteMode = false,
    ): int {
        $columns = OwnerTupleColumns::forModelClass(CartModel::class);
        $owners = DB::table($table)
            ->select([
                $columns->ownerTypeColumn,
                $columns->ownerIdColumn,
            ])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return OwnerContext::withOwner(null, fn (): int => $this->handleForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: $dryRun,
                batchSize: $batchSize,
                deleteMode: $deleteMode,
                ownerType: null,
                ownerId: null,
            ));
        }

        $ownerBatches = [];
        $totalCount = 0;

        foreach ($owners as $row) {
            $parsed = OwnerTupleParser::fromRow(
                row: $row,
                columns: $columns,
                allowMalformed: true,
            );

            if ($parsed->isUnresolved()) {
                if ($strictOwnerTuples) {
                    $this->error(sprintf(
                        'Malformed owner tuple encountered (owner_type: %s, owner_id: %s).',
                        $row->{$columns->ownerTypeColumn} ?? 'null',
                        $row->{$columns->ownerIdColumn} === null ? 'null' : (string) $row->{$columns->ownerIdColumn},
                    ));

                    return self::FAILURE;
                }

                $this->warn(sprintf(
                    'Skipping malformed owner tuple while clearing abandoned carts (owner_type: %s, owner_id: %s).',
                    $row->{$columns->ownerTypeColumn} ?? 'null',
                    $row->{$columns->ownerIdColumn} === null ? 'null' : (string) $row->{$columns->ownerIdColumn},
                ));

                continue;
            }

            $owner_type = $parsed->owner_type;
            $owner_id = $parsed->owner_id;

            $count = $this->countForOwner($table, $useExpired, $days, $now, $owner_type, $owner_id, $deleteMode);
            $ownerBatches[] = [
                'owner_type' => $owner_type,
                'owner_id' => $owner_id,
                'count' => $count,
            ];
            $totalCount += $count;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        if ($totalCount === 0) {
            $this->info('No abandoned carts found.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} abandoned carts to clear.");

        if ($dryRun) {
            $processedCount = 0;
            foreach ($ownerBatches as $batch) {
                if ($batch['count'] === 0) {
                    continue;
                }

                $processedCount += $this->processForOwner(
                    table: $table,
                    useExpired: $useExpired,
                    days: $days,
                    now: $now,
                    dryRun: true,
                    batchSize: $batchSize,
                    deleteMode: $deleteMode,
                    ownerType: $batch['owner_type'],
                    ownerId: $batch['owner_id'],
                );
            }

            $this->info("Would delete {$processedCount} abandoned carts.");

            return self::SUCCESS;
        }

        $confirmed = $this->confirm('Are you sure you want to delete these carts?');
        if (! $confirmed) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $processedCount = 0;

        foreach ($ownerBatches as $batch) {
            if ($batch['count'] === 0) {
                continue;
            }

            $processedCount += $this->processForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: false,
                batchSize: $batchSize,
                deleteMode: $deleteMode,
                ownerType: $batch['owner_type'],
                ownerId: $batch['owner_id'],
            );
        }

        $this->info("Successfully deleted {$processedCount} carts.");

        return self::SUCCESS;
    }

    private function handleForOwner(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        bool $dryRun,
        int $batchSize,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode = false,
    ): int {
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $totalCount = $this->countForOwner($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode);

        if ($totalCount === 0) {
            $this->info('No abandoned carts found.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} abandoned carts to clear.");

        if ($dryRun) {
            $processedCount = $this->processForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: true,
                batchSize: $batchSize,
                deleteMode: $deleteMode,
                ownerType: $ownerType,
                ownerId: $ownerId,
            );

            $this->info("Would delete {$processedCount} abandoned carts.");

            return self::SUCCESS;
        }

        $confirmed = $this->confirm('Are you sure you want to delete these carts?');
        if (! $confirmed) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $processedCount = $this->processForOwner(
            table: $table,
            useExpired: $useExpired,
            days: $days,
            now: $now,
            dryRun: false,
            batchSize: $batchSize,
            deleteMode: $deleteMode,
            ownerType: $ownerType,
            ownerId: $ownerId,
        );

        $this->info("Successfully deleted {$processedCount} carts.");

        return self::SUCCESS;
    }

    private function countForOwner(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode = false,
    ): int {
        $query = $this->baseQuery($table, $useExpired, $days, $now, $deleteMode);
        CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);

        return $query->count();
    }

    private function processForOwner(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        bool $dryRun,
        int $batchSize,
        bool $deleteMode,
        ?string $ownerType,
        string | int | null $ownerId,
    ): int {
        $query = $this->baseQuery($table, $useExpired, $days, $now, $deleteMode);
        CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);

        $processedCount = 0;

        $actionLabel = 'Deleting';
        $progressLabel = $dryRun ? "Simulating {$actionLabel}..." : "{$actionLabel} abandoned carts...";

        progress(
            label: $progressLabel,
            steps: $query->clone()->pluck('id')->chunk($batchSize),
            callback: function ($chunk) use (&$processedCount, $dryRun, $deleteMode, $table, $ownerType, $ownerId): void {
                if ($dryRun) {
                    $processedCount += $chunk->count();

                    return;
                }

                $ids = $chunk->toArray();

                $deleteQuery = DB::table($table)->whereIn('id', $ids);
                CartOwnerScope::applyForOwner($deleteQuery, $ownerType, $ownerId);

                if ($deleteMode) {
                    $deleteQuery->whereNotNull('abandoned_at');
                }

                $processed = $deleteQuery->delete();
                $processedCount += $processed;
            }
        );

        return $processedCount;
    }

    private function baseQuery(string $table, bool $useExpired, int $days, CarbonInterface $now, bool $deleteMode = false): Builder
    {
        if ($deleteMode) {
            return DB::table($table)
                ->whereNotNull('abandoned_at');
        }

        if ($useExpired) {
            return DB::table($table)
                ->whereNull('expired_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now);
        }

        $cutoffDate = $now->subDays($days);

        return DB::table($table)
            ->whereNull('abandoned_at')
            ->where('updated_at', '<', $cutoffDate);
    }
}
