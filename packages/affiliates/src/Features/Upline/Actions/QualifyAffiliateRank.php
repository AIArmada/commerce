<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRankMaintenance;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use AIArmada\Affiliates\Features\Upline\Api\QualifiedRank;
use AIArmada\Affiliates\Features\Upline\Api\RankQualificationApi;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Features\Upline\Queries\RankQualificationQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class QualifyAffiliateRank implements RankQualificationApi
{
    public function __construct(private RankQualificationQuery $query, private Dispatcher $events) {}

    public function evaluate(string $affiliateId): ?QualifiedRank
    {
        $rank = $this->query->evaluate($this->profile($affiliateId));

        return $rank?->toQualifiedRank();
    }

    public function processAllRankUpgrades(bool $dryRun = false): int
    {
        $changed = 0;

        foreach (app(AffiliateReportingReader::class)->scopedIds(spl_object_id($this->connection()), commercialOnly: true) as $id) {
            $changed += (int) $this->process($id, $dryRun);
        }

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
            $affiliate = $this->profile($affiliateId, lock: true);
            if (! $affiliate->commercial) {
                throw new InvalidArgumentException('A non-commercial affiliate cannot receive a rank.');
            }

            $rank = $rankId === null ? null : $this->query->ranksFor($affiliate)->sharedLock()->findOrFail($rankId);
            $this->change($affiliate, $rank, RankQualificationReason::Manual);
        }, attempts: 3);
    }

    /** @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int} */
    public function calculateMetrics(string $affiliateId, ?CarbonImmutable $from = null, ?string $currency = null): array
    {
        return $this->query->calculateMetrics($this->profile($affiliateId), $from, $currency);
    }

    private function process(string $affiliateId, bool $dryRun): bool
    {
        return $this->connection()->transaction(function () use ($affiliateId, $dryRun): bool {
            $affiliate = $this->profile($affiliateId, lock: true);
            if (! $affiliate->commercial) {
                return false;
            }

            $rank = $this->query->evaluate($affiliate, lockRanks: ! $dryRun);
            if ($affiliate->rankId === $rank?->id) {
                return false;
            }

            if (! $dryRun) {
                $this->change($affiliate, $rank, RankQualificationReason::Qualified);
            }

            return true;
        }, attempts: 3);
    }

    private function profile(string $affiliateId, bool $lock = false): AffiliateProfile
    {
        $profiles = app(AffiliateProfileReader::class);
        $identity = spl_object_id($this->connection());

        return ($lock ? $profiles->lock($affiliateId, $identity) : $profiles->find($affiliateId, $identity))
            ?? throw new ModelNotFoundException('Affiliate not found.');
    }

    private function connection(): Connection
    {
        $connection = (new AffiliateRank)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        if ($connection !== (new AffiliateRankHistory)->getConnection()) {
            throw new LogicException('Rank assignment requires a shared database connection.');
        }

        return $connection;
    }

    private function change(AffiliateProfile $affiliate, ?AffiliateRank $rank, RankQualificationReason $reason): void
    {
        if ($affiliate->rankId === $rank?->id) {
            return;
        }

        $oldRank = $affiliate->rankId === null ? null : $this->query->ranksFor($affiliate)->sharedLock()->find($affiliate->rankId);
        AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->reference->id,
            'from_rank_id' => $affiliate->rankId,
            'to_rank_id' => $rank?->id,
            'reason' => $reason,
            'qualified_at' => CarbonImmutable::now(),
        ]);

        app(AffiliateRankMaintenance::class)->assignRank($affiliate->reference->id, $rank?->id, spl_object_id($this->connection()));

        $this->events->dispatch(new AffiliateRankChanged(
            affiliateId: $affiliate->reference->id,
            fromRank: $oldRank?->toQualifiedRank(),
            toRank: $rank?->toQualifiedRank(),
            reason: $reason,
        ));
    }
}
