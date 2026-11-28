<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

interface VoucherWalletReader
{
    /** @param list<string> $voucherIds
     * @return array<string, WalletVoucherState>
     */
    public function readMany(array $voucherIds): array;
}
