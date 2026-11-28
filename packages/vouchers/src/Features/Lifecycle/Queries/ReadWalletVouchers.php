<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherWalletReader;
use AIArmada\Vouchers\Features\Lifecycle\Api\WalletVoucherState;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;

final class ReadWalletVouchers implements VoucherWalletReader
{
    use QueriesVouchers;

    /** @param list<string> $voucherIds
     * @return array<string, WalletVoucherState>
     */
    public function readMany(array $voucherIds): array
    {
        if ($voucherIds === []) {
            return [];
        }

        $states = [];

        foreach ($this->voucherQuery(withUsageCount: true)->whereKey(array_values(array_unique($voucherIds)))->get() as $voucher) {
            $states[$voucher->id] = new WalletVoucherState(
                VoucherSnapshot::fromModel($voucher),
                $voucher->isExpired(),
                $voucher->canBeRedeemed(),
            );
        }

        return $states;
    }
}
