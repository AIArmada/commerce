<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

interface VoucherDeletion
{
    public function handle(string $voucherId): bool;

    /** @param list<string> $voucherIds */
    public function handleMany(array $voucherIds): void;
}
