<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Events;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherUsageData;
use AIArmada\Vouchers\Events\Concerns\HasVoucherEventData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VoucherUsageRecorded
{
    use Dispatchable;
    use HasVoucherEventData;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly VoucherData $voucher,
        public readonly VoucherUsageData $usage,
    ) {
        $this->initializeEventData();
    }

    public function getDiscountAmountCents(): ?int
    {
        return $this->usage->discountAmount;
    }

    public function getEventType(): string
    {
        return 'voucher.usage_recorded';
    }
}
