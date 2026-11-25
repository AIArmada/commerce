<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Features\Upline\Api\QualifiedRank;
use AIArmada\Affiliates\Features\Upline\Api\RankQualificationApi;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Features\Upline\Queries\RankQualificationQuery;
use AIArmada\Affiliates\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class QualifyAffiliateRank implements RankQualificationApi
{
    public function __construct(private RankQualificationQuery $query, private Dispatcher $events) {}

    public function evaluate(string $affiliateId): ?QualifiedRank
    {
        $rank = $this->query->evaluate(Affiliate::query()->findOrFail($affiliateId));

        return $rank?->toQualifiedRank();
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
        if ($rankId !== null && ! Str::isUuid($rankId)) {
            throw new InvalidArgumentException('The selected affiliate rank is not accessible.');
        }

        $this->connection()->transaction(function () use ($affiliateId, $rankId): void {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            if (! $affiliate->isCommercialPurpose()) {
                throw new InvalidArgumentException('A non-commercial affiliate cannot receive a rank.');
            }

            $rank = $rankId === null ? null : $this->query->ranksFor($affiliate)->sharedLock()->findOrFail($rankId);
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
        return $this->connection()->transaction(function () use ($affiliateId, $dryRun): bool {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            if (! $affiliate->isCommercialPurpose()) {
                return false;
            }

            $rank = $this->query->evaluate($affiliate, lockRanks: ! $dryRun);
            if ($affiliate->rank_id === $rank?->id) {
                return false;
            }

            if (! $dryRun) {
                $this->change($affiliate, $rank, RankQualificationReason::Qualified);
            }

            return true;
        }, attempts: 3);
    }

    private function connection(): Connection
    {
        $connection = (new Affiliate)->getConnection();

        if ($connection !== (new AffiliateRank)->getConnection()
            || $connection !== (new AffiliateRankHistory)->getConnection()) {
            throw new LogicException('Rank assignment requires a shared database connection.');
        }

        return $connection;
    }

    private function change(Affiliate $affiliate, ?AffiliateRank $rank, RankQualificationReason $reason): void
    {
        if ($affiliate->rank_id === $rank?->id) {
            return;
        }

        $oldRank = $affiliate->rank_id === null ? null : $this->query->ranksFor($affiliate)->sharedLock()->find($affiliate->rank_id);
        AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $affiliate->rank_id,
            'to_rank_id' => $rank?->id,
            'reason' => $reason,
            'qualified_at' => CarbonImmutable::now(),
        ]);

        $affiliate->update(['rank_id' => $rank?->id]);

        $this->events->dispatch(new AffiliateRankChanged(
            affiliateId: (string) $affiliate->getKey(),
            fromRank: $oldRank?->toQualifiedRank(),
            toRank: $rank?->toQualifiedRank(),
            reason: $reason,
        ));
    }
}
