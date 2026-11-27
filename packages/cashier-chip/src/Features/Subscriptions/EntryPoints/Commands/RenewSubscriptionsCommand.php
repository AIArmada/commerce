<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\EntryPoints\Commands;

use AIArmada\CashierChip\Features\Subscriptions\Actions\RenewSubscriptions;
use Illuminate\Console\Command;

final class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'cashier-chip:renew-subscriptions
                            {--dry-run : Show what would be renewed without actually charging}
                            {--grace-hours=0 : Hours of grace period before considering subscription due}';

    protected $description = 'Atomically claim and process CHIP subscription renewals';

    public function __construct(private readonly RenewSubscriptions $renewSubscriptions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $graceHours = max(0, (int) $this->option('grace-hours'));
        $this->info('Processing CHIP subscription renewals...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No claims or charges will be made');
        }

        $result = $this->renewSubscriptions->handle(
            $dryRun,
            $graceHours,
            function (string $id): void {
                $this->line("Would claim renewal for subscription {$id}");
            },
        );

        $this->info(sprintf(
            'Renewal complete: %d renewed, %d failed, %d unknown, %d skipped.',
            $result['renewed'],
            $result['failed'],
            $result['unknown'],
            $result['skipped'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
