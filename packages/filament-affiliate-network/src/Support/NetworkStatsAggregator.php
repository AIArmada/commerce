<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Features\Attribution\Api\AttributionApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\EnrollmentApi;
use AIArmada\AffiliateNetwork\Features\Ledger\Api\LedgerApi;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class NetworkStatsAggregator
{
    /**
     * Network-wide totals for the admin dashboard.
     *
     * Revenue is grouped by link currency. Single-currency networks pass the
     * raw sum through; mixed-currency revenue is converted to the network
     * default for display, or null when a rate is missing so the widget
     * shows the breakdown instead of a blended number.
     *
     * @return array{activeSites: int, activeOffers: int, pendingApplications: int, totalClicks: int, totalConversions: int, totalRevenue: int|null, conversionRate: float, revenueFormatted: string, revenueCurrency: string, revenueConverted: bool, revenueByCurrency: array<string, int>, pendingLegsCount: int, openStatementsCount: int}
     */
    public static function aggregate(): array
    {
        return OwnerContext::withOwner(null, function (): array {
            $rows = app(AttributionApi::class)->networkLinkTotalsUnscoped();
            $totalClicks = array_sum(array_column($rows, 'clicks'));
            $totalConversions = array_sum(array_column($rows, 'conversions'));
            $revenue = self::aggregateRevenue($rows);
            $activeSites = app(SitesApi::class)->countVerifiedSitesUnscoped();
            $activeOffers = app(CatalogApi::class)->countPublishedOffersUnscoped();
            $pendingApplications = app(EnrollmentApi::class)->countPendingApplicationsUnscoped();
            $ledger = app(LedgerApi::class);
            $pendingLegsCount = $ledger->countPendingLegsUnscoped();
            $openStatementsCount = $ledger->countIssuedStatementsUnscoped();

            $conversionRate = $totalClicks > 0
                ? round(($totalConversions / $totalClicks) * 100, 2)
                : 0;

            return [
                'activeSites' => $activeSites,
                'activeOffers' => $activeOffers,
                'pendingApplications' => $pendingApplications,
                'totalClicks' => $totalClicks,
                'totalConversions' => $totalConversions,
                'totalRevenue' => $revenue['total'],
                'conversionRate' => $conversionRate,
                'revenueFormatted' => $revenue['total'] === null
                    ? '—'
                    : MoneyFormatter::formatMinor($revenue['total'], $revenue['currency']),
                'revenueCurrency' => $revenue['currency'],
                'revenueConverted' => $revenue['converted'],
                'revenueByCurrency' => $revenue['by_currency'],
                'pendingLegsCount' => $pendingLegsCount,
                'openStatementsCount' => $openStatementsCount,
            ];
        });
    }

    /**
     * @param  list<array{currency: string|null, revenue: int, clicks: int, conversions: int}>  $rows
     * @return array{total: int|null, currency: string, converted: bool, by_currency: array<string, int>}
     */
    private static function aggregateRevenue(array $rows): array
    {
        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = is_string($row['currency']) && mb_trim($row['currency']) !== ''
                ? mb_strtoupper(mb_trim($row['currency']))
                : self::defaultCurrency();

            $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + (int) $row['revenue'];
        }

        if (count($byCurrency) === 1) {
            $only = (string) array_key_first($byCurrency);

            return [
                'total' => $byCurrency[$only] ?? 0,
                'currency' => $only,
                'converted' => false,
                'by_currency' => $byCurrency,
            ];
        }

        $default = self::defaultCurrency();
        $total = app(CurrencyConverter::class)->totalMinor($byCurrency, $default);

        return [
            'total' => $total,
            'currency' => $default,
            'converted' => $total !== null && $byCurrency !== [],
            'by_currency' => $byCurrency,
        ];
    }

    private static function defaultCurrency(): string
    {
        return mb_strtoupper((string) config('affiliate-network.currency.default', 'MYR'));
    }
}
