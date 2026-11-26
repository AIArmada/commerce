<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use DateTimeInterface;

interface ConversionStatisticsReader
{
    public function revenueWindow(string $affiliateId, string $fallbackCurrency, int $connectionIdentity, DateTimeInterface $from): ConversionRevenueWindow;

    public function balanceAudit(string $affiliateId, string $currency, int $connectionIdentity): ConversionBalanceAudit;

    public function assertConnection(int $connectionIdentity): void;

    /**
     * @param  list<string>  $affiliateIds
     * @param  list<string>|null  $statuses
     */
    public function count(array $affiliateIds, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null, ?array $statuses = null, bool $includeGlobal = true, bool $inclusiveUntil = false): int;

    /**
     * @param  list<string>|null  $statuses
     * @return list<ConversionCurrencyTotals>
     */
    public function totals(string $affiliateId, string $fallbackCurrency, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null, ?array $statuses = null, bool $includeGlobal = true, bool $inclusiveUntil = false): array;
}
