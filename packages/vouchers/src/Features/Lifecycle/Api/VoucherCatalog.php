<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherData;

interface VoucherCatalog
{
    public function connectionName(): string;

    public function find(string $code, ?string $voucherId = null): ?VoucherData;

    public function resolveId(string $code, ?string $voucherId = null): ?string;

    public function lockForWalletClaim(string $code, ?string $voucherId, string $holderType, string $holderId): string;

    public function invalidate(string $code): void;

    public function getRemainingUses(string $code): int;
}
