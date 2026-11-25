<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Payouts\Api\ScheduledPayoutSweep;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Closure;
use Throwable;

final readonly class RunScheduledPayoutSweep implements ScheduledPayoutSweep
{
    public function __construct(private ClaimScheduledPayout $claim, private BalanceReader $balances) {}

    public function sweep(bool $dryRun = true, ?string $affiliateId = null, int $minimum = 0): array
    {
        $affiliateId = $affiliateId !== null && mb_trim($affiliateId) !== '' ? mb_trim($affiliateId) : null;

        if ($affiliateId !== null && (bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
        }

        return $this->run($dryRun, $affiliateId, $minimum);
    }

    /**
     * @param  (Closure(string, string, string): void)|null  $onOutcome
     * @return array{processed: int, skipped: int, errors: int}
     */
    public function run(bool $dryRun, ?string $affiliateId, int $minimum, ?Closure $onOutcome = null): array
    {
        $minimum = max(0, $minimum);
        $summary = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($this->balances->payoutCandidates($minimum, $affiliateId) as $balance) {
            $id = $balance->affiliateId;
            $currency = $balance->currency;

            try {
                $eligible = $dryRun
                    ? $this->claim->isEligibleSnapshot($id, $minimum, $currency)
                    : $this->claim->handle($id, $minimum, $currency) !== null;
                $outcome = $eligible ? 'processed' : 'skipped';
            } catch (Throwable $exception) {
                report($exception);
                $outcome = 'errors';
            }

            $summary[$outcome]++;
            $onOutcome?->__invoke($outcome, $id, $currency);
        }

        return $summary;
    }
}
