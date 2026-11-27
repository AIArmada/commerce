<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\EntryPoints\Listeners;

use AIArmada\Vouchers\Actions\RecordVoucherApplication;
use AIArmada\Vouchers\Events\VoucherApplied;

final readonly class IncrementVoucherAppliedCount
{
    public function __construct(private RecordVoucherApplication $recordVoucherApplication) {}

    public function handle(VoucherApplied $event): void
    {
        $this->recordVoucherApplication->handle(
            $event->voucher->id,
            $event->voucher->ownerId,
            $event->voucher->ownerType,
        );
    }
}
