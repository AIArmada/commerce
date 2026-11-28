<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Api;

interface WalletTimestampTransition
{
    public function handle(string $walletId, bool $redeemed): void;

    /** @param list<string> $walletIds */
    public function handleMany(array $walletIds, bool $redeemed): void;
}
