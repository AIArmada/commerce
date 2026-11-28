<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

interface VoucherLookupInvalidation
{
    public function forgetIdentity(string $code, ?string $ownerType, ?string $ownerId, string $connectionName): void;
}
