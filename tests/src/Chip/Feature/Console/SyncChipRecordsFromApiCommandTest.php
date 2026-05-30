<?php

declare(strict_types=1);

use AIArmada\Chip\Actions\SyncChipRecordsFromApiAction;
use AIArmada\Chip\Commands\SyncChipRecordsFromApiCommand;

describe('SyncChipRecordsFromApiCommand', function (): void {
    it('runs with explicit purchase ids in dry-run mode', function (): void {
        $firstPurchaseId = '11111111-1111-4111-8111-111111111111';
        $secondPurchaseId = '22222222-2222-4222-8222-222222222222';

        $this->mock(SyncChipRecordsFromApiAction::class, function ($mock) use ($firstPurchaseId, $secondPurchaseId): void {
            $mock->shouldReceive('handle')
                ->once()
                ->withArgs(
                    static fn (array $purchaseIds, bool $dryRun, bool $overwriteExisting, array $statuses, mixed $onProgress): bool => $purchaseIds === [$firstPurchaseId, $secondPurchaseId]
                    && $dryRun === true
                    && $overwriteExisting === false
                    && $statuses === []
                    && $onProgress instanceof Closure
                )
                ->andReturn([
                    'processed' => 2,
                    'synced' => 2,
                    'skipped' => 0,
                    'failed' => 0,
                    'errors' => [],
                ]);
        });

        $this->artisan(SyncChipRecordsFromApiCommand::class, [
            '--purchase-id' => [$firstPurchaseId, $secondPurchaseId],
            '--dry-run' => true,
        ])
            ->expectsOutput('Processing 2 purchase(s)...')
            ->expectsOutput('Processed: 2')
            ->expectsOutput('Synced: 2')
            ->expectsOutput('Skipped: 0')
            ->expectsOutput('Failed: 0')
            ->assertExitCode(0);
    });

    it('passes normalized status filters to sync action', function (): void {
        $purchaseId = '33333333-3333-4333-8333-333333333333';

        $this->mock(SyncChipRecordsFromApiAction::class, function ($mock) use ($purchaseId): void {
            $mock->shouldReceive('handle')
                ->once()
                ->withArgs(
                    static fn (array $purchaseIds, bool $dryRun, bool $overwriteExisting, array $statuses, mixed $onProgress): bool => $purchaseIds === [$purchaseId]
                    && $dryRun === true
                    && $overwriteExisting === false
                    && $statuses === ['paid', 'refunded']
                    && $onProgress instanceof Closure
                )
                ->andReturn([
                    'processed' => 1,
                    'synced' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                    'errors' => [],
                ]);
        });

        $this->artisan(SyncChipRecordsFromApiCommand::class, [
            '--purchase-id' => [$purchaseId],
            '--status' => ['PAID,refunded'],
            '--dry-run' => true,
        ])
            ->expectsOutput('Processing 1 purchase(s)...')
            ->expectsOutput('Processed: 1')
            ->expectsOutput('Synced: 1')
            ->expectsOutput('Skipped: 0')
            ->expectsOutput('Failed: 0')
            ->assertExitCode(0);
    });

    it('returns success when no purchase ids can be resolved', function (): void {
        $this->artisan(SyncChipRecordsFromApiCommand::class, [
            '--from' => '2030-01-01',
            '--to' => '2030-01-01',
        ])
            ->expectsOutput('No CHIP purchase IDs found to sync.')
            ->assertExitCode(0);
    });
});
