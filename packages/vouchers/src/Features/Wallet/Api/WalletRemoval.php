<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Api;

interface WalletRemoval
{
    public function handle(string $code, string $holderType, string $holderId): bool;
}
