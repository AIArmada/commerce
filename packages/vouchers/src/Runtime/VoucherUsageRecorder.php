<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;

interface VoucherUsageRecorder
{
    /** @param array<string, mixed>|null $metadata */
    public function handle(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?Voucher $voucherModel = null,
    ): VoucherUsage;
}
