<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\PerformanceBonusRule;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TopPerformerBonusRule implements PerformanceBonusRule
{
    public function bonusType(): string
    {
        return 'top_performer';
    }

    public function isEnabled(): bool
    {
        return (bool) (config('affiliates.bonuses.top_performer.enabled', true));
    }

    public function calculate(CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal = false): array
    {
        $config = config('affiliates.bonuses.top_performer', []);

        if (! $this->isEnabled()) {
            return [];
        }

        $bonuses = [];
        $leaderboard = $this->getLeaderboard($from, $to, 3, $includeGlobal);

        foreach ($leaderboard as $entry) {
            if ($entry['total_revenue'] < ($config['min_revenue'] ?? 100000)) {
                continue;
            }

            $position = $entry['rank'];
            $bonusAmount = $config['positions'][$position] ?? 0;

            if ($bonusAmount > 0) {
                $bonuses[] = [
                    'bonus_type' => $this->bonusType(),
                    'affiliate_id' => $entry['affiliate_id'],
                    'affiliate_name' => $entry['affiliate_name'],
                    'amount_minor' => $bonusAmount,
                    'reason' => "Top Performer Bonus - #{$position} for " . $from->format('F Y'),
                    'metrics' => [
                        'position' => $position,
                        'total_revenue' => $entry['total_revenue'],
                        'total_conversions' => $entry['total_conversions'],
                        'period' => $from->format('Y-m'),
                    ],
                ];
            }
        }

        return $bonuses;
    }

    private function getLeaderboard(CarbonImmutable $from, CarbonImmutable $to, int $limit, bool $includeGlobal): array
    {
        $conversionsTable = (new AffiliateConversion)->getTable();
        $affiliatesTable = (new Affiliate)->getTable();
        $revenueExpression = "COALESCE(NULLIF({$conversionsTable}.value_minor, 0), {$conversionsTable}.total_minor, 0)";

        $query = DB::table($conversionsTable)
            ->join($affiliatesTable, "{$conversionsTable}.affiliate_id", '=', "{$affiliatesTable}.id")
            ->select([
                "{$affiliatesTable}.id as affiliate_id",
                "{$affiliatesTable}.name as affiliate_name",
                DB::raw("SUM({$revenueExpression}) as total_revenue"),
                DB::raw("COUNT({$conversionsTable}.id) as total_conversions"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ApprovedConversion::value())
            ->where("{$affiliatesTable}.status", AffiliateStatus::normalize(Active::class))
            ->groupBy("{$affiliatesTable}.id", "{$affiliatesTable}.name")
            ->orderByDesc('total_revenue')
            ->limit($limit);

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Top performer bonuses require an owner context or explicit global context.',
            );

            $includeGlobal = (bool) config('affiliates.owner.include_global', false);
            OwnerQuery::applyToQueryBuilder(
                $query,
                $owner,
                $includeGlobal,
                "{$conversionsTable}.owner_type",
                "{$conversionsTable}.owner_id",
            );
            OwnerQuery::applyToQueryBuilder(
                $query,
                $owner,
                $includeGlobal,
                "{$affiliatesTable}.owner_type",
                "{$affiliatesTable}.owner_id",
            );
        }

        return $query->get()
            ->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'affiliate_id' => (string) $row->affiliate_id,
                'affiliate_name' => (string) $row->affiliate_name,
                'total_revenue' => (int) $row->total_revenue,
                'total_conversions' => (int) $row->total_conversions,
            ])
            ->toArray();
    }
}
