<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Actions\SyncChipRecordsFromApiAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SyncChipRecordsFromApiCommand extends Command
{
    protected $signature = 'chip:sync-from-api
                            {--purchase-id=* : Specific CHIP purchase IDs to sync}
                            {--status=* : Optional CHIP purchase status filter(s), e.g. --status=paid or --status=paid,refunded}
                            {--from= : Optional checkout session created_at lower bound}
                            {--to= : Optional checkout session created_at upper bound}
                            {--limit=500 : Maximum number of purchase IDs from checkout sessions}
                            {--overwrite-existing : Re-sync even when purchase already exists locally}
                            {--dry-run : Fetch from CHIP API without writing local tables}';

    protected $description = 'Sync chip_clients, chip_purchases, and chip_payments from CHIP API';

    public function handle(SyncChipRecordsFromApiAction $action): int
    {
        /** @var array<int, string> $purchaseIds */
        $purchaseIds = collect($this->option('purchase-id'))
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($purchaseIds === []) {
            $checkoutSessionModel = 'AIArmada\\Checkout\\Models\\CheckoutSession';

            if (! class_exists($checkoutSessionModel)) {
                $this->warn('No CHIP purchase IDs provided and checkout integration is unavailable in this installation.');

                return self::SUCCESS;
            }

            $query = $checkoutSessionModel::query()
                ->where('selected_payment_gateway', 'chip')
                ->whereNotNull('payment_id')
                ->orderByDesc('created_at');

            $from = $this->option('from');
            if (is_string($from) && $from !== '') {
                $query->where('created_at', '>=', CarbonImmutable::parse($from));
            }

            $to = $this->option('to');
            if (is_string($to) && $to !== '') {
                $query->where('created_at', '<=', CarbonImmutable::parse($to));
            }

            $limit = (int) $this->option('limit');

            $purchaseIds = $query
                ->limit($limit > 0 ? $limit : 500)
                ->pluck('payment_id')
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                ->unique()
                ->values()
                ->all();
        }

        if ($purchaseIds === []) {
            $this->warn('No CHIP purchase IDs found to sync.');

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
}
