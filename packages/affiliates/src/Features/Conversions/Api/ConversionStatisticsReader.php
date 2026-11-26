<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use DateTimeInterface;

interface ConversionStatisticsReader
{
    /** @param list<string> $affiliateIds */
    public function count(array $affiliateIds, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null): int;

    /** @return list<ConversionCurrencyTotals> */
    public function totals(string $affiliateId, string $fallbackCurrency, int $connectionIdentity, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null): array;
}
