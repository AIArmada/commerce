<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Api;

interface WalletStatistics
{
    /** @return array{total: int, claimed: int, redeemed: int, available: int} */
    public function forVoucher(string $voucherId): array;
}
