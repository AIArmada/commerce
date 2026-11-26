<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionCurrencyTotals;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Models\AffiliateConversion;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class ReadConversionStatistics implements ConversionStatisticsReader
{
    public function count(array $affiliateIds, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null, ?array $statuses = null, bool $includeGlobal = true, bool $inclusiveUntil = false): int
    {
        $query = $this->query($connectionIdentity, $from, $until, $statuses, $includeGlobal, $inclusiveUntil);

        return $affiliateIds === [] ? 0 : $query->whereIn('affiliate_id', $affiliateIds)->count();
    }

    public function totals(string $affiliateId, string $fallbackCurrency, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null, ?array $statuses = null, bool $includeGlobal = true, bool $inclusiveUntil = false): array
    {
        $rows = $this->query($connectionIdentity, $from, $until, $statuses, $includeGlobal, $inclusiveUntil)
            ->where('affiliate_id', $affiliateId)
            ->toBase()
            ->selectRaw('commission_currency AS currency, COUNT(*) AS conversion_count, COALESCE(SUM(value_minor), 0) AS revenue_minor, COALESCE(SUM(commission_minor), 0) AS commission_minor')
            ->groupBy('commission_currency')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $currency = mb_strtoupper(mb_trim((string) $row->currency));
            $currency = $currency === '' ? $fallbackCurrency : $currency;
            $previous = $totals[$currency] ?? null;

            $totals[$currency] = new ConversionCurrencyTotals(
                $currency,
                ($previous?->count ?? 0) + (int) $row->conversion_count,
                ($previous?->revenueMinor ?? 0) + (int) $row->revenue_minor,
                ($previous?->commissionMinor ?? 0) + (int) $row->commission_minor,
            );
        }

        ksort($totals);

        return array_values($totals);
    }

    public function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Conversion statistics require a shared database connection.');
        }
    }

    /**
     * @param list<string>|null $statuses
     * @return Builder<AffiliateConversion>
     */
    private function query(int $connectionIdentity, ?DateTimeInterface $from, ?DateTimeInterface $until, ?array $statuses, bool $includeGlobal, bool $inclusiveUntil): Builder
    {
        $this->assertConnection($connectionIdentity);
        $query = AffiliateConversion::query()->forOwner(includeGlobal: $includeGlobal);

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($until !== null) {
            $query->where('occurred_at', $inclusiveUntil ? '<=' : '<', $until);
        }

        return $query;
    }
}
