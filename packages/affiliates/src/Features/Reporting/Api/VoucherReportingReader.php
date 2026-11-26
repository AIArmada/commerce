<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

interface VoucherReportingReader
{
    public function available(): bool;

    public function resolve(string $voucherCode, ?string $orderReference): ?VoucherReportingContext;

    /** @return array<string, string> */
    public function affiliateOptions(): array;

    /** @return array<string, string> */
    public function attributionOptions(string $field): array;
}
