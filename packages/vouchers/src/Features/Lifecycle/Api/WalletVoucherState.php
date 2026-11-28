<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherData;

final readonly class WalletVoucherState
{
    public function __construct(
        public VoucherData $voucher,
        public bool $expired,
        public bool $redeemable,
    ) {}
}
