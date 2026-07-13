<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Actions\Payouts\ClaimScheduledPayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Console\Command;
use Throwable;

final class ProcessScheduledPayoutsCommand extends Command
{
    protected $signature = 'affiliates:process-payouts
        {--dry-run : Show what would be processed without reserving balances}
        {--affiliate= : Process payouts for a specific affiliate ID}
        {--min-amount= : Minimum amount threshold in minor units}';

    protected $description = 'Atomically claim scheduled affiliate payout operations';

    public function __construct(private readonly ClaimScheduledPayout $claimScheduledPayout)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $affiliateId = is_string($this->option('affiliate')) ? $this->option('affiliate') : null;
        $minimum = (int) ($this->option('min-amount') ?? config('affiliates.payouts.minimum_amount', 5000));

        $runner = new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );
        $summary = $runner->run(fn (): array => $this->processScoped($affiliateId, $minimum, $dryRun))
            ?? ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        $this->info("Processed: {$summary['processed']}");
        $this->info("Skipped: {$summary['skipped']}");

        if ($summary['errors'] > 0) {
            $this->error("Errors: {$summary['errors']}");
        }

        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{processed:int,skipped:int,errors:int} */
    private function processScoped(?string $affiliateId, int $minimum, bool $dryRun): array
    {
        $query = Affiliate::query()
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->whereHas('balance', static function ($query) use ($minimum): void {
                $query->where('available_minor', '>=', $minimum);
            });

        if ($affiliateId !== null && $affiliateId !== '') {
            $query->whereKey($affiliateId);
        }

        $summary = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        $query->select('id')->orderBy('id')->chunkById(100, function ($affiliates) use ($minimum, $dryRun, &$summary): void {
            foreach ($affiliates as $affiliate) {
                if ($dryRun) {
                    if ($this->claimScheduledPayout->isEligibleSnapshot((string) $affiliate->id, $minimum)) {
                        $summary['processed']++;
                        $this->line("Would atomically claim payout for affiliate {$affiliate->id}");
                    } else {
                        $summary['skipped']++;
                    }

                    continue;
                }

                try {
                    $operation = $this->claimScheduledPayout->handle((string) $affiliate->id, $minimum);
                    $operation === null ? ++$summary['skipped'] : ++$summary['processed'];
                } catch (Throwable) {
                    $summary['errors']++;
                    $this->error("Payout claim failed for affiliate {$affiliate->id}.");
                }
            }
        }, 'id');

        return $summary;
    }
}
