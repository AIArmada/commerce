<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AffiliateStatsAggregator
{
    /**
     * Commission legs group by currency. Single-currency programs pass raw
     * sums through; mixed programs convert to the widget currency, or null
     * when a rate is missing so the widget shows a dash instead of a blend.
     *
     * @return array{
     *     total_affiliates:int,
     *     active_affiliates:int,
     *     pending_affiliates:int,
     *     total_conversions:int,
     *     pending_commission_minor:int|null,
     *     paid_commission_minor:int|null,
     *     total_commission_minor:int|null,
     *     commission_currency:string,
     *     commission_converted:bool,
     *     commission_by_currency:array<string, array{pending_minor:int, paid_minor:int, total_minor:int}>,
     *     conversion_rate:float|null
     * }
     */
    public function overview(): array
    {
        $owner = $this->resolveOwner();

        $activeStatus = AffiliateStatus::fromString(Active::class)->getValue();
        $pendingStatus = AffiliateStatus::fromString(Pending::class)->getValue();

        $affiliateRow = $this->affiliateQuery($owner)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', [$activeStatus])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [$pendingStatus])
            ->first();

        $pendingConversion = PendingConversion::value();
        $paidConversion = PaidConversion::value();
        $approvedConversion = ApprovedConversion::value();

        $conversionRows = $this->conversionQuery($owner)
            ->toBase()
            ->selectRaw('commission_currency as currency, COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN commission_minor ELSE 0 END), 0) as pending_commission', [$pendingConversion])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN commission_minor ELSE 0 END), 0) as paid_commission', [$paidConversion])
            ->selectRaw('COALESCE(SUM(commission_minor), 0) as total_commission')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as approved', [$approvedConversion, $paidConversion])
            ->groupBy('commission_currency')
            ->get();

        $totalAffiliates = (int) ($affiliateRow->total ?? 0);
        $activeAffiliates = (int) ($affiliateRow->active ?? 0);
        $pendingAffiliates = (int) ($affiliateRow->pending ?? 0);
        $totalConversions = 0;
        $approved = 0;
        $byCurrency = [];

        foreach ($conversionRows as $row) {
            $currency = is_string($row->currency) && mb_trim($row->currency) !== ''
                ? mb_strtoupper(mb_trim($row->currency))
                : $this->displayCurrency();

            $byCurrency[$currency] ??= ['pending_minor' => 0, 'paid_minor' => 0, 'total_minor' => 0];
            $byCurrency[$currency]['pending_minor'] += (int) $row->pending_commission;
            $byCurrency[$currency]['paid_minor'] += (int) $row->paid_commission;
            $byCurrency[$currency]['total_minor'] += (int) $row->total_commission;
            $totalConversions += (int) $row->total;
            $approved += (int) $row->approved;
        }

        $money = $this->summarizeCommissions($byCurrency);

        $conversionRate = $totalConversions > 0
            ? ($approved / $totalConversions) * 100
            : null;

        return [
            'total_affiliates' => $totalAffiliates,
            'active_affiliates' => $activeAffiliates,
            'pending_affiliates' => $pendingAffiliates,
            'total_conversions' => $totalConversions,
            'pending_commission_minor' => $money['pending'],
            'paid_commission_minor' => $money['paid'],
            'total_commission_minor' => $money['total'],
            'commission_currency' => $money['currency'],
            'commission_converted' => $money['converted'],
            'commission_by_currency' => $byCurrency,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * @param  array<string, array{pending_minor: int, paid_minor: int, total_minor: int}>  $byCurrency
     * @return array{pending: int|null, paid: int|null, total: int|null, currency: string, converted: bool}
     */
    private function summarizeCommissions(array $byCurrency): array
    {
        if (count($byCurrency) === 1) {
            $only = (string) array_key_first($byCurrency);
            $single = $byCurrency[$only];

            return [
                'pending' => $single['pending_minor'],
                'paid' => $single['paid_minor'],
                'total' => $single['total_minor'],
                'currency' => $only,
                'converted' => false,
            ];
        }

        $display = $this->displayCurrency();
        $converter = app(CurrencyConverter::class);
        $pending = [];
        $paid = [];
        $total = [];

        foreach ($byCurrency as $currency => $money) {
            $pending[$currency] = $money['pending_minor'];
            $paid[$currency] = $money['paid_minor'];
            $total[$currency] = $money['total_minor'];
        }

        $pendingTotal = $converter->totalMinor($pending, $display);
        $paidTotal = $converter->totalMinor($paid, $display);
        $grandTotal = $converter->totalMinor($total, $display);

        return [
            'pending' => $pendingTotal,
            'paid' => $paidTotal,
            'total' => $grandTotal,
            'currency' => $display,
            'converted' => $pendingTotal !== null && $paidTotal !== null && $grandTotal !== null && $byCurrency !== [],
        ];
    }

    private function displayCurrency(): string
    {
        return mb_strtoupper((string) config('filament-affiliates.widgets.currency', 'MYR'));
    }

    private function affiliateQuery(?Model $owner): Builder
    {
        return Affiliate::query()->forOwner($owner);
    }

    private function conversionQuery(?Model $owner): Builder
    {
        return AffiliateConversion::query()->forOwner($owner);
    }

    private function resolveOwner(): ?Model
    {
        if (! config('affiliates.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
