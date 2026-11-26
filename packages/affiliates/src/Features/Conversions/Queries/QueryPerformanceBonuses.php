<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Features\Commissions\Api\PerformanceBonusCalculation;
use AIArmada\Affiliates\Features\Conversions\Api\PerformanceBonusQueries;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class QueryPerformanceBonuses implements PerformanceBonusQueries
{
    public function __construct(
        private PerformanceBonusCalculation $commissionRules,
    ) {}

    public function calculateBonuses(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null
    ): array {
        $from = $from ?? CarbonImmutable::now()->startOfMonth();
        $to = $to ?? CarbonImmutable::now()->endOfMonth();

        $bonuses = [];

        foreach (CommissionRuleType::performanceBonusCases() as $type) {
            $ruleBonuses = $this->commissionRules->calculatePerformanceBonuses($type, $from, $to);

            foreach ($ruleBonuses as $bonus) {
                $bonuses[$bonus['affiliate_id'] . '_' . $bonus['bonus_type']] = $bonus;
            }
        }

        return $bonuses;
    }

    public function getLeaderboard(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        int $limit = 10
    ): array {
        $from = $from ?? CarbonImmutable::now()->startOfMonth();
        $to = $to ?? CarbonImmutable::now()->endOfMonth();

        $conversion = new AffiliateConversion;
        $affiliate = new Affiliate;
        $connection = $conversion->getConnection();

        if ($connection !== $affiliate->getConnection()) {
            throw new LogicException('Performance bonus queries require a shared database connection.');
        }

        $conversionsTable = $conversion->getTable();
        $affiliatesTable = $affiliate->getTable();
        $grammar = $connection->getQueryGrammar();
        $purposeColumn = $grammar->wrap("{$affiliatesTable}.purpose");
        $valueColumn = $grammar->wrap("{$conversionsTable}.value_minor");
        $idColumn = $grammar->wrap("{$conversionsTable}.id");
        $commissionColumn = $grammar->wrap("{$conversionsTable}.commission_minor");
        $revenueExpression = "COALESCE({$valueColumn}, 0)";

        $query = $connection->table($conversionsTable)
            ->join($affiliatesTable, "{$conversionsTable}.affiliate_id", '=', "{$affiliatesTable}.id")
            ->select([
                "{$affiliatesTable}.id as affiliate_id",
                "{$affiliatesTable}.name as affiliate_name",
                "{$affiliatesTable}.code as affiliate_code",
                $connection->raw("SUM({$revenueExpression}) as total_revenue"),
                $connection->raw("COUNT({$idColumn}) as total_conversions"),
                $connection->raw("SUM({$commissionColumn}) as total_commissions"),
                $connection->raw("AVG({$revenueExpression}) as avg_order_value"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ApprovedConversion::value())
            ->where("{$affiliatesTable}.status", AffiliateStatus::normalize(Active::class))
            ->where(static function ($query) use ($affiliatesTable, $purposeColumn): void {
                $query->whereNull("{$affiliatesTable}.purpose")
                    ->orWhereRaw("LOWER(TRIM({$purposeColumn})) = ?", [Affiliate::PURPOSE_GENERAL]);
            })
            ->groupBy("{$affiliatesTable}.id", "{$affiliatesTable}.name", "{$affiliatesTable}.code")
            ->orderByDesc('total_revenue')
            ->orderBy("{$affiliatesTable}.id")
            ->limit($limit);

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Performance bonus queries require an owner context or explicit global context.',
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
            })->all();
    }
}
