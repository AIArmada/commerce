<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\CommerceSupport\Data\RecordReference;
use Akaunting\Money\Money;

interface VoucherRedemption
{
    /** @param array<string, mixed>|null $metadata */
    public function redeemManually(
        string $code,
        Money $discountAmount,
        ?string $reference = null,
        ?array $metadata = null,
        ?RecordReference $redeemedBy = null,
        ?string $notes = null,
    ): void;

    public function redeem(
        string $code,
        string $orderId,
        ?int $discountAmount = null,
        ?string $currency = null,
        ?string $voucherId = null,
    ): void;
}
