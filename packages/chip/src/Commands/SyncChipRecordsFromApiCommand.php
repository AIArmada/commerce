<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Actions\SyncChipRecordsFromApiAction;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class SyncChipRecordsFromApiCommand extends Command
{
    protected $signature = 'chip:sync-from-api
                            {--purchase-id=* : Specific CHIP purchase IDs to sync}
                            {--status=* : Optional CHIP purchase status filter(s), e.g. --status=paid or --status=paid,refunded}
                            {--overwrite-existing : Re-sync even when purchase already exists locally}
                            {--dry-run : Fetch from CHIP API without writing local tables}
                            {--owner-type= : Owner model type to sync under when owner scoping is enabled}
                            {--owner-id= : Owner model id to sync under when owner scoping is enabled}';

    protected $description = 'Sync chip_clients, chip_purchases, and chip_payments from CHIP API';

    public function handle(SyncChipRecordsFromApiAction $action): int
    {
        /** @var array<int, string> $purchaseIds */
        $purchaseIds = collect($this->option('purchase-id'))
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($purchaseIds === []) {
            $this->warn('Provide one or more --purchase-id values to sync CHIP purchases.');

            return self::SUCCESS;
        }

        /** @var array<int, string> $statusFilters */
        $statusFilters = collect($this->option('status'))
            ->flatMap(static function (mixed $status): array {
                if (! is_string($status) || $status === '') {
                    return [];
                }

                return array_filter(array_map('trim', explode(',', $status)));
            })
            ->map(static fn (string $status): string => mb_strtolower($status))
            ->unique()
            ->values()
            ->all();

        $owner = $this->resolveOwnerOption();

        if ($owner === false) {
            return self::FAILURE;
        }

        $this->line(sprintf('Processing %d purchase(s)...', count($purchaseIds)));
        $this->output->progressStart(count($purchaseIds));

        $summary = $action->handle(
            purchaseIds: $purchaseIds,
            dryRun: (bool) $this->option('dry-run'),
            overwriteExisting: (bool) $this->option('overwrite-existing'),
            statuses: $statusFilters,
            onProgress: function (): void {
                $this->output->progressAdvance();
            },
            owner: $owner,
        );

        $this->output->progressFinish();
        $this->newLine();

        $this->info(sprintf('Processed: %d', $summary['processed']));
        $this->info(sprintf('Synced: %d', $summary['synced']));
        $this->line(sprintf('Skipped: %d', $summary['skipped']));
        $this->line(sprintf('Failed: %d', $summary['failed']));

        if ($summary['errors'] !== []) {
            $this->newLine();
            $this->error('Errors:');

            foreach ($summary['errors'] as $error) {
                $this->line('- ' . $error);
            }
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveOwnerOption(): Model | null | false
    {
        $ownerType = $this->option('owner-type');
        $ownerId = $this->option('owner-id');

        if ($ownerType === null && $ownerId === null) {
            return null;
        }

        if (! is_string($ownerType) || $ownerType === '' || (! is_string($ownerId) && ! is_int($ownerId))) {
            $this->error('Both --owner-type and --owner-id are required to sync under an owner.');

            return false;
        }

        $owner = OwnerContext::fromTypeAndId($ownerType, $ownerId);

        if (! $owner instanceof Model) {
            $this->error('The requested owner could not be resolved.');

            return false;
        }

        return $owner;
    }
}
