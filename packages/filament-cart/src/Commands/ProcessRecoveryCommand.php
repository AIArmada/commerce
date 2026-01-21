<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\Cart\Models\RecoveryAttempt;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Services\RecoveryScheduler;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class ProcessRecoveryCommand extends Command
{
    protected $signature = 'cart:process-recovery
                            {--limit=100 : Maximum number of attempts to process}
                            {--retry-failed : Also retry failed attempts}';

    protected $description = 'Process scheduled recovery attempts';

    public function handle(RecoveryScheduler $scheduler): int
    {
        $limit = (int) $this->option('limit');
        $retryFailed = $this->option('retry-failed');

        $this->info('Processing scheduled recovery attempts...');

        $result = $this->processForOwners($scheduler);

        $this->info("Processed: {$result['processed']} attempts");

        if ($result['failed'] > 0) {
            $this->warn("Failed: {$result['failed']} attempts");
        }

        if ($retryFailed) {
            $this->line('');
            $this->info('Retry of failed attempts is not yet implemented.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{processed: int, failed: int}
     */
    private function processForOwners(RecoveryScheduler $scheduler): array
    {
        if (! RecoveryAttempt::ownerScopingEnabled()) {
            return $scheduler->processScheduledAttempts();
        }

        if (OwnerContext::resolve() !== null) {
            return $scheduler->processScheduledAttempts();
        }

        $owners = RecoveryAttempt::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return $scheduler->processScheduledAttempts();
        }

        $totals = [
            'processed' => 0,
            'failed' => 0,
        ];

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);

            $result = OwnerContext::withOwner(
                $owner,
                fn (): array => $scheduler->processScheduledAttempts()
            );

            $totals['processed'] += $result['processed'];
            $totals['failed'] += $result['failed'];
        }

        return $totals;
    }

    private function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }
}
