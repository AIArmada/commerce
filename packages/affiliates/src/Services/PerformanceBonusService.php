<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PerformanceBonusService
{
    private array $bonusRules;

    public function __construct(
        #[Tag('affiliates.performance_bonus_rule')]
        iterable $bonusRules = [],
    ) {
        $this->bonusRules = [];
        foreach ($bonusRules as $rule) {
            $this->bonusRules[$rule->bonusType()] = $rule;
        }
    }

    public function calculateBonuses(
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $bonuses = [];

        foreach ($this->bonusRules as $rule) {
            if (! $rule->isEnabled()) {
                continue;
            }

            $ruleBonuses = $rule->calculate(
                CarbonImmutable::createFromInterface($from),
                CarbonImmutable::createFromInterface($to),
            );

            foreach ($ruleBonuses as $bonus) {
                $bonuses[$bonus['affiliate_id'] . '_' . $bonus['bonus_type']] = $bonus;
            }
        }

        return $bonuses;
    }

    public function awardBonuses(array $bonuses): int
    {
        return (int) DB::transaction(function () use ($bonuses): int {
            $awarded = 0;

            foreach ($bonuses as $bonus) {
                $affiliate = Affiliate::query()->forOwner()->find($bonus['affiliate_id']);

                if (! $affiliate || ! $affiliate->status->equals(Active::class)) {
                    continue;
                }

                $period = (string) ($bonus['metrics']['period'] ?? now()->format('Y-m'));
                $performanceBonusKey = implode(':', [
                    'performance-bonus',
                    $affiliate->id,
                    $bonus['bonus_type'],
                    $period,
                ]);

                AffiliateBalance::firstOrCreate(
                    [
                        'affiliate_id' => $affiliate->id,
                        'currency' => $affiliate->currency ?? config('affiliates.currency.default', 'USD'),
                    ],
                    [
                        'holding_minor' => 0,
                        'available_minor' => 0,
                        'lifetime_earnings_minor' => 0,
                        'minimum_payout_minor' => config('affiliates.payouts.minimum_amount', 5000),
                    ]
                );

                $conversion = AffiliateConversion::firstOrCreate(
                    ['performance_bonus_key' => $performanceBonusKey],
                    [
                        'affiliate_id' => $affiliate->id,
                        'affiliate_code' => $affiliate->code,
                        'external_reference' => 'BONUS-' . $period . '-' . mb_strtoupper(mb_substr(md5($performanceBonusKey), 0, 8)),
                        'performance_bonus_key' => $performanceBonusKey,
                        'subtotal_minor' => 0,
                        'commission_minor' => $bonus['amount_minor'],
                        'status' => ApprovedConversion::class,
                        'occurred_at' => now(),
                        'metadata' => [
                            'type' => 'performance_bonus',
                            'bonus_type' => $bonus['bonus_type'],
                            'reason' => $bonus['reason'],
                            'metrics' => $bonus['metrics'],
                        ],
                    ],
                );

                $awarded += (int) $conversion->wasRecentlyCreated;
            }

            return $awarded;
        });
    }

    public function getLeaderboard(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $limit = 10
    ): Collection {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $conversionsTable = (new AffiliateConversion)->getTable();
        $affiliatesTable = (new Affiliate)->getTable();
        $revenueExpression = "COALESCE(NULLIF({$conversionsTable}.value_minor, 0), {$conversionsTable}.total_minor, 0)";

        $query = DB::table($conversionsTable)
            ->join($affiliatesTable, "{$conversionsTable}.affiliate_id", '=', "{$affiliatesTable}.id")
            ->select([
                "{$affiliatesTable}.id as affiliate_id",
                "{$affiliatesTable}.name as affiliate_name",
                "{$affiliatesTable}.code as affiliate_code",
                DB::raw("SUM({$revenueExpression}) as total_revenue"),
                DB::raw("COUNT({$conversionsTable}.id) as total_conversions"),
                DB::raw("SUM({$conversionsTable}.commission_minor) as total_commissions"),
                DB::raw("AVG({$revenueExpression}) as avg_order_value"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ApprovedConversion::value())
            ->where("{$affiliatesTable}.status", AffiliateStatus::normalize(Active::class))
            ->groupBy("{$affiliatesTable}.id", "{$affiliatesTable}.name", "{$affiliatesTable}.code")
            ->orderByDesc('total_revenue')
            ->limit($limit);

        $this->applyOwnerScopeToQuery($query, "{$conversionsTable}.owner_type", "{$conversionsTable}.owner_id");
        $this->applyOwnerScopeToQuery($query, "{$affiliatesTable}.owner_type", "{$affiliatesTable}.owner_id");

        return $query
            ->get()
            ->map(function ($row, $index) {
                return [
                    'rank' => $index + 1,
                    'affiliate_id' => (string) $row->affiliate_id,
                    'affiliate_name' => (string) $row->affiliate_name,
                    'affiliate_code' => (string) $row->affiliate_code,
                    'total_revenue' => (int) $row->total_revenue,
                    'total_conversions' => (int) $row->total_conversions,
                    'total_commissions' => (int) $row->total_commissions,
                    'avg_order_value' => round((float) $row->avg_order_value, 2),
                ];
            });
    }

    private function applyOwnerScopeToQuery(Builder $query, string $ownerTypeColumn, string $ownerIdColumn): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        OwnerQuery::applyToQueryBuilder($query, $owner, $includeGlobal, $ownerTypeColumn, $ownerIdColumn);
    }
}
