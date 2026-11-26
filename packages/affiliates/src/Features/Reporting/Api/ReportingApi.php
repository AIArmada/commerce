<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

use DateTimeInterface;

interface ReportingApi
{
    /**
     * Money legs are grouped by the conversion currency. Single-currency
     * results pass raw sums through untouched; mixed-currency totals are
     * converted to the default currency for display, or null when a rate
     * is missing so callers render the per-currency breakdown instead.
     *
     * @return array{attributions: int, conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}
     */
    public function getSummary(DateTimeInterface $startDate, DateTimeInterface $endDate): array;

    /**
     * @return array<int, array{affiliate_id: string, affiliate_code: string, name: string|null, currency: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getTopAffiliates(DateTimeInterface $startDate, DateTimeInterface $endDate, int $limit = 10): array;

    /**
     * @return array<int, array{date: string, currency: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getConversionTrend(DateTimeInterface $startDate, DateTimeInterface $endDate): array;

    /**
     * @return array{sources: array<string, int>, campaigns: array<string, int>}
     */
    public function getTrafficSources(DateTimeInterface $startDate, DateTimeInterface $endDate): array;

    /**
     * @return array<int, array{subject_type: string, subject_key: string, subject_title_snapshot: string|null, visits: int, attributions: int, conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}>
     */
    public function getTopSubjects(DateTimeInterface $startDate, DateTimeInterface $endDate, int $limit = 10): array;

    /**
     * @return array<string, mixed>
     */
    public function affiliateSummary(string $affiliateId): array;
}
