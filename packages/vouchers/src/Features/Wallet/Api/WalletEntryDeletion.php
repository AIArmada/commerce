<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Api;

interface WalletEntryDeletion
{
    public function handle(string $walletId): bool;

    /** @param list<string> $walletIds */
    public function handleMany(array $walletIds): void;
}
