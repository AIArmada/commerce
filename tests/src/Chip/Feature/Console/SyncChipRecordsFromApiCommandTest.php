<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Features\Collect\EntryPoints\Commands\SyncChipRecordsFromApiCommand;

describe('SyncChipRecordsFromApiCommand', function (): void {
    it('runs with explicit purchase ids in dry-run mode', function (): void {
        $firstPurchaseId = '11111111-1111-4111-8111-111111111111';
        $secondPurchaseId = '22222222-2222-4222-8222-222222222222';

        Chip::shouldReceive('getPurchase')->once()->with($firstPurchaseId)->andReturn(PurchaseData::from(['id' => $firstPurchaseId, 'status' => 'paid', 'purchase' => ['currency' => 'MYR', 'total' => 1000]]));
        Chip::shouldReceive('getPurchase')->once()->with($secondPurchaseId)->andReturn(PurchaseData::from(['id' => $secondPurchaseId, 'status' => 'refunded', 'purchase' => ['currency' => 'MYR', 'total' => 1000]]));

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

    it('accepts normalized comma-separated status filters', function (): void {
        $purchaseId = '33333333-3333-4333-8333-333333333333';

        Chip::shouldReceive('getPurchase')->once()->with($purchaseId)->andReturn(PurchaseData::from(['id' => $purchaseId, 'status' => 'refunded', 'purchase' => ['currency' => 'MYR', 'total' => 1000]]));

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

    it('returns success when no purchase ids are provided', function (): void {
        $this->artisan(SyncChipRecordsFromApiCommand::class)
            ->expectsOutput('Provide one or more --purchase-id values to sync CHIP purchases.')
            ->assertExitCode(0);
    });
});
