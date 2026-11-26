<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\EntryPoints\Console;

use AIArmada\Affiliates\Features\Payouts\Actions\RunScheduledPayoutSweep;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use Illuminate\Console\Command;

final class ProcessScheduledPayoutsCommand extends Command
{
    protected $signature = 'affiliates:process-payouts
        {--dry-run : Show what would be processed without reserving balances}
        {--affiliate= : Process payouts for a specific affiliate ID}
        {--min-amount= : Minimum amount floor in minor units, applied in each balance currency}';

    protected $description = 'Atomically claim scheduled affiliate payout operations';

    public function __construct(private readonly RunScheduledPayoutSweep $sweep)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $affiliateId = is_string($this->option('affiliate')) ? $this->option('affiliate') : null;
        $override = $this->option('min-amount');
        $minimum = $override !== null && $override !== '' ? (int) $override : 0;

        $summary = app(AffiliateReportingReader::class)->summarizeForOwners(fn (): array => $this->sweep->run($dryRun, $affiliateId, $minimum, function (string $outcome, string $id, string $currency) use ($dryRun): void {
            if ($dryRun && $outcome === 'processed') {
                $this->line("Would atomically claim {$currency} payout for affiliate {$id}");
            } elseif ($outcome === 'errors') {
                $this->error("Payout claim failed for affiliate {$id} ({$currency}).");
            }
        }))
            ?? ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        $this->info("Processed: {$summary['processed']}");
        $this->info("Skipped: {$summary['skipped']}");

        if ($summary['errors'] > 0) {
            $this->error("Errors: {$summary['errors']}");
        }

        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
