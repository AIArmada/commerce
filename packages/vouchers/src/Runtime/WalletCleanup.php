<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

interface WalletCleanup
{
    public function handle(string $voucherId): void;
}
