<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\Vouchers\Data\VoucherUsageData;
use Akaunting\Money\Money;

interface VoucherUsageRecorder
{
    /** @param array<string, mixed>|null $metadata */
    public function handle(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?RecordReference $redeemedBy = null,
        ?string $notes = null,
        ?string $voucherId = null,
    ): VoucherUsageData;
}
