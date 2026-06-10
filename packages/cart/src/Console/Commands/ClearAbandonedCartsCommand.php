<?php

declare(strict_types=1);

namespace AIArmada\Cart\Console\Commands;

use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Support\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use Carbon\CarbonImmutable;
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
                          {--expired : Only delete carts that have passed their expires_at timestamp}
                          {--dry-run : Show what would be deleted without actually deleting}
                          {--delete : Physically delete carts that already have abandoned_at set (skips detection)}
                          {--all-owners : Process every owner when no owner context is available}
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
            info('Delete mode: Physically deleting carts with abandoned_at set');
        } elseif ($useExpired) {
            info('Clearing expired carts (using expires_at column)');
        } else {
            $cutoffDate = $now->subDays($days);
            info("Clearing carts abandoned before: {$cutoffDate->format('Y-m-d H:i:s')}");
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
                $columns->ownerTypeColumn . ' as owner_type',
                $columns->ownerIdColumn . ' as owner_id',
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
                columns: new OwnerTupleColumns,
                allowMalformed: true,
            );

            if ($parsed->isUnresolved()) {
                if ($strictOwnerTuples) {
                    $this->error(sprintf(
                        'Malformed owner tuple encountered (owner_type: %s, owner_id: %s).',
                        $row->owner_type ?? 'null',
                        $row->owner_id === null ? 'null' : (string) $row->owner_id,
                    ));

                    return self::FAILURE;
                }

                warning(sprintf(
                    'Skipping malformed owner tuple while clearing abandoned carts (owner_type: %s, owner_id: %s).',
                    $row->owner_type ?? 'null',
                    $row->owner_id === null ? 'null' : (string) $row->owner_id,
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
            warning('DRY RUN MODE - No data will be modified');
        }

        if ($totalCount === 0) {
            info('No abandoned carts found.');

            return self::SUCCESS;
        }

        $label = $deleteMode ? 'delete' : 'mark as abandoned';
        info("Found {$totalCount} carts to {$label}.");

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

            info("Would {$label} {$processedCount} carts.");

            return self::SUCCESS;
        }

        $confirmed = confirm("Are you sure you want to {$label} these carts?");
        if (! $confirmed) {
            info('Operation cancelled.');

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

        info("Successfully {$label}d {$processedCount} carts.");

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
            warning('DRY RUN MODE - No data will be modified');
        }

        $totalCount = $this->countForOwner($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode);

        if ($totalCount === 0) {
            info('No abandoned carts found.');

            return self::SUCCESS;
        }

        $label = $deleteMode ? 'delete' : 'mark as abandoned';
        info("Found {$totalCount} carts to {$label}.");

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

            info("Would {$label} {$processedCount} carts.");

            return self::SUCCESS;
        }

        $confirmed = confirm("Are you sure you want to {$label} these carts?");
        if (! $confirmed) {
            info('Operation cancelled.');

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

        info("Successfully {$label}d {$processedCount} carts.");

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

        $actionLabel = $deleteMode ? 'Deleting' : 'Marking as abandoned';
        $progressLabel = $dryRun ? "Simulating {$actionLabel}..." : "{$actionLabel} carts...";

        progress(
            label: $progressLabel,
            steps: $query->clone()->pluck('id')->chunk($batchSize),
            callback: function ($chunk) use (&$processedCount, $dryRun, $deleteMode, $table, $ownerType, $ownerId): void {
                if ($dryRun) {
                    $processedCount += $chunk->count();

                    return;
                }

                $ids = $chunk->toArray();

                // Defense-in-depth: re-apply owner scope to prevent cross-tenant operations
                if ($deleteMode) {
                    $deleteQuery = DB::table($table)->whereIn('id', $ids);
                    CartOwnerScope::applyForOwner($deleteQuery, $ownerType, $ownerId);
                    $processed = $deleteQuery->delete();
                    $processedCount += $processed;
                } else {
                    $updateQuery = DB::table($table)->whereIn('id', $ids);
                    CartOwnerScope::applyForOwner($updateQuery, $ownerType, $ownerId);
                    $processed = $updateQuery->update([
                        'abandoned_at' => CarbonImmutable::now(),
                        'updated_at' => CarbonImmutable::now(),
                    ]);
                    $processedCount += $processed;
                }
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
