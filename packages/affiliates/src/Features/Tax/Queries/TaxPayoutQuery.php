<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Queries;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\CompletedPayout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class TaxPayoutQuery
{
    public function thresholdCurrency(): string
    {
        return mb_strtoupper((string) config('affiliates.tax.1099_threshold_currency', 'USD'));
    }

    /** @return list<string> */
    public function affiliatesRequiring1099(int $year): array
    {
        $eligible = $this->annualQuery($year)->where('currency', $this->thresholdCurrency())
            ->select('payee_id')->groupBy('payee_id')->havingRaw('SUM(total_minor) >= ?', [(int) config('affiliates.tax.1099_threshold', 60000)]);

        return Affiliate::query()->whereIn('id', $eligible)->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
    }

    public function total(string $affiliateId, int $year, ?string $currency = null): int
    {
        return (int) $this->annualQuery($year)->where('payee_id', $affiliateId)->where('currency', $currency ?? $this->thresholdCurrency())->sum('total_minor');
    }

    /** @return list<array{reference: string, currency: string, amount_minor: int}> */
    public function excluded(string $affiliateId, int $year, ?string $currency = null): array
    {
        return $this->annualQuery($year)->where('payee_id', $affiliateId)->where('currency', '!=', $currency ?? $this->thresholdCurrency())
            ->orderBy('paid_at')->get(['reference', 'currency', 'total_minor'])
            ->map(static fn (AffiliatePayout $payout): array => ['reference' => $payout->reference, 'currency' => $payout->currency, 'amount_minor' => $payout->total_minor])->values()->all();
    }

    /** @return Builder<AffiliatePayout> */
    private function annualQuery(int $year): Builder
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();

        return AffiliatePayout::query()->where('payee_type', (new Affiliate)->getMorphClass())
            ->where('status', CompletedPayout::value())->where('paid_at', '>=', $start)->where('paid_at', '<', $start->addYear());
    }
}
