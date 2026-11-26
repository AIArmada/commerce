<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use DateTimeInterface;

interface ConversionReportingReader
{
    public function available(): bool;

    /** @return list<ConversionReportRow> */
    public function money(?DateTimeInterface $start = null, ?DateTimeInterface $end = null, ?string $affiliateId = null): array;

    /** @return list<ConversionReportRow> */
    public function byAffiliate(DateTimeInterface $start, DateTimeInterface $end): array;

    /** @return list<ConversionReportRow> */
    public function approvedCommercialByAffiliate(DateTimeInterface $start, DateTimeInterface $end, bool $includeGlobal): array;

    /** @return list<ConversionReportRow> */
    public function byDay(DateTimeInterface $start, DateTimeInterface $end): array;

    /** @return list<ConversionReportRow> */
    public function bySubject(DateTimeInterface $start, DateTimeInterface $end): array;

    public function traffic(?DateTimeInterface $start = null, ?DateTimeInterface $end = null, ?string $affiliateId = null): ConversionTrafficBreakdown;

    public function voucher(string $voucherCode, ?string $orderReference): ?VoucherConversionRecord;
}
