<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Api;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\Vouchers\Data\WalletClaimData;

interface WalletClaim
{
    /** @param array<string, mixed>|null $metadata */
    public function handle(string $code, RecordReference $holder, ?array $metadata = null, ?string $voucherId = null): WalletClaimData;
}
