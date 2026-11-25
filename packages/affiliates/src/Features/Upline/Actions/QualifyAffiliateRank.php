<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Features\Upline\Api\RankQualificationApi;
use AIArmada\Affiliates\Features\Upline\Api\QualifiedRank;
use AIArmada\Affiliates\Features\Upline\Queries\RankQualificationQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;

final readonly class QualifyAffiliateRank implements RankQualificationApi
{
    public function __construct(private RankQualificationQuery $query, private Dispatcher $events) {}

    public function evaluate(string $affiliateId): ?QualifiedRank
    {
        $rank = $this->query->evaluate(Affiliate::query()->findOrFail($affiliateId));

        return $rank === null ? null : new QualifiedRank((string) $rank->getKey(), $rank->name, $rank->level, $rank->currencyCode());
    }

    public function processAllRankUpgrades(bool $dryRun = false): int
    {
        $changed = 0;

        Affiliate::query()->whereCommercialPurpose()->select('id')->chunkById(100, function ($affiliates) use (&$changed, $dryRun): void {
            foreach ($affiliates as $affiliate) {
                $changed += (int) $this->process((string) $affiliate->getKey(), $dryRun);
            }
        });

        return $changed;
    }

    public function processRankChange(string $affiliateId): void
    {
        $this->process($affiliateId, false);
    }

    /** @param iterable<string> $affiliateIds */
    public function processBatch(iterable $affiliateIds): void
    {
        foreach ($affiliateIds as $affiliateId) {
            $this->processRankChange($affiliateId);
        }
    }

    public function assignRank(string $affiliateId, ?string $rankId): void
    {
        (new Affiliate)->getConnection()->transaction(function () use ($affiliateId, $rankId): void {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            if (! $affiliate->isCommercialPurpose()) {
                throw new InvalidArgumentException('A non-commercial affiliate cannot receive a rank.');
            }

            $rank = $rankId === null ? null : AffiliateRank::query()->forOwner()->findOrFail($rankId);
            $this->change($affiliate, $rank, RankQualificationReason::Manual);
        }, attempts: 3);
    }

    /** @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int} */
    public function calculateMetrics(string $affiliateId, ?CarbonImmutable $from = null, ?string $currency = null): array
    {
        return $this->query->calculateMetrics(Affiliate::query()->findOrFail($affiliateId), $from, $currency);
    }

    private function process(string $affiliateId, bool $dryRun): bool
    {
        return (new Affiliate)->getConnection()->transaction(function () use ($affiliateId, $dryRun): bool {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            if (! $affiliate->isCommercialPurpose()) {
                return false;
            }

            $rank = $this->query->evaluate($affiliate);
            if ($affiliate->rank_id === $rank?->id) {
                return false;
            }

            if (! $dryRun) {
                $this->change($affiliate, $rank, RankQualificationReason::Qualified);
            }

            return true;
        }, attempts: 3);
    }

    private function change(Affiliate $affiliate, ?AffiliateRank $rank, RankQualificationReason $reason): void
    {
        if ($affiliate->rank_id === $rank?->id) {
            return;
        }

        $oldRank = $affiliate->rank;
        AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $affiliate->rank_id,
            'to_rank_id' => $rank?->id,
            'reason' => $reason,
            'qualified_at' => CarbonImmutable::now(),
        ]);

        $affiliate->update(['rank_id' => $rank?->id]);
        $affiliate->setRelation('rank', $rank);

        $this->events->dispatch(new AffiliateRankChanged(
            affiliate: $affiliate,
            fromRank: $oldRank,
            toRank: $rank,
            reason: $reason,
        ));
    }
}
