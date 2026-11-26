<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionBalanceAudit;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionCurrencyTotals;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionRevenueWindow;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;
use stdClass;

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

        return $this->foldTotals($rows, $fallbackCurrency);
    }

    public function totalsForAffiliates(array $affiliateIds, string $fallbackCurrency, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null, bool $includeGlobal = true): array
    {
        $this->assertConnection($connectionIdentity);
        $totals = [];

        foreach (array_chunk(array_values(array_unique($affiliateIds)), 500) as $ids) {
            $rows = $this->query($connectionIdentity, $from, $until, null, $includeGlobal, true)
                ->whereIn('affiliate_id', $ids)
                ->toBase()
                ->selectRaw('commission_currency AS currency, COUNT(*) AS conversion_count, COALESCE(SUM(value_minor), 0) AS revenue_minor, COALESCE(SUM(commission_minor), 0) AS commission_minor')
                ->groupBy('commission_currency')
                ->get();

            foreach ($this->foldTotals($rows, $fallbackCurrency) as $row) {
                $previous = $totals[$row->currency] ?? null;

                $totals[$row->currency] = new ConversionCurrencyTotals(
                    $row->currency,
                    ($previous?->count ?? 0) + $row->count,
                    ($previous?->revenueMinor ?? 0) + $row->revenueMinor,
                    ($previous?->commissionMinor ?? 0) + $row->commissionMinor,
                );
            }
        }

        ksort($totals);

        return array_values($totals);
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<ConversionCurrencyTotals>
     */
    private function foldTotals(Collection $rows, string $fallbackCurrency): array
    {
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

    public function revenueWindow(string $affiliateId, string $fallbackCurrency, int $connectionIdentity, DateTimeInterface $from): ConversionRevenueWindow
    {
        $rows = $this->query($connectionIdentity, null, null, null, true, false)
            ->where('affiliate_id', $affiliateId)
            ->toBase()
            ->selectRaw('commission_currency AS currency, COALESCE(SUM(value_minor), 0) AS lifetime, COALESCE(SUM(CASE WHEN occurred_at >= ? THEN COALESCE(value_minor, 0) ELSE 0 END), 0) AS personal', [$from])
            ->groupBy('commission_currency')
            ->get();

        $personal = [];
        $lifetime = [];

        foreach ($rows as $row) {
            $currency = mb_strtoupper(mb_trim((string) $row->currency));
            $currency = $currency === '' ? $fallbackCurrency : $currency;
            $personal[$currency] = ($personal[$currency] ?? 0) + (int) $row->personal;
            $lifetime[$currency] = ($lifetime[$currency] ?? 0) + (int) $row->lifetime;
        }

        return new ConversionRevenueWindow($personal, $lifetime);
    }

    public function balanceAudit(string $affiliateId, string $currency, int $connectionIdentity): ConversionBalanceAudit
    {
        $rows = $this->query($connectionIdentity, null, null, null, true, false)
            ->where('affiliate_id', $affiliateId)
            ->whereRaw('UPPER(commission_currency) = ?', [mb_strtoupper($currency)])
            ->where(static function (Builder $query): void {
                $query->where('status', ApprovedConversion::value())
                    ->orWhere(static fn (Builder $query): Builder => $query->where('status', PaidConversion::value())->whereNotNull('affiliate_payout_id'))
                    ->orWhere(static fn (Builder $query): Builder => $query->where('status', ReversedConversion::value())->whereNull('affiliate_payout_id')->whereNotNull('paid_at'));
            })
            ->toBase()
            ->selectRaw('status, affiliate_payout_id, COALESCE(SUM(commission_minor), 0) AS total')
            ->groupBy('status', 'affiliate_payout_id')
            ->get();

        $approved = 0;
        $clawbacks = 0;
        $paid = [];

        foreach ($rows as $row) {
            $amount = (int) $row->total;

            if ($row->status === ApprovedConversion::value()) {
                $approved += $amount;
            } elseif ($row->status === ReversedConversion::value()) {
                $clawbacks += $amount;
            } elseif ($row->affiliate_payout_id !== null) {
                $paid[(string) $row->affiliate_payout_id] = $amount;
            }
        }

        return new ConversionBalanceAudit($approved, $clawbacks, $paid);
    }

    public function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Conversion statistics require a shared database connection.');
        }
    }

    /**
     * @param  list<string>|null  $statuses
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
