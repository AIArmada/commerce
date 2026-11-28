<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Runtime\VoucherLookup;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;

final class ReadVoucherCatalog implements VoucherCatalog
{
    use QueriesVouchers;

    public function __construct(private readonly VoucherLookup $lookupCache) {}

    public function find(string $code, ?string $voucherId = null): ?VoucherData
    {
        if ($voucherId !== null) {
            $voucher = $this->voucherIdentityQuery($code, $voucherId)->first();

            return $voucher === null ? null : VoucherSnapshot::fromModel($voucher);
        }

        $normalizedCode = $this->normalizeCode($code);

        return $this->lookupCache->remember(
            $normalizedCode,
            $this->resolveOwner(),
            $this->shouldIncludeGlobal(),
            function () use ($normalizedCode): ?VoucherData {
                $voucher = $this->voucherQuery()
                    ->where('code', $normalizedCode)
                    ->first();

                return $voucher ? VoucherSnapshot::fromModel($voucher) : null;
            },
        );
    }

    public function invalidate(string $code): void
    {
        $this->lookupCache->forget($this->normalizeCode($code), $this->resolveOwner());
    }

    public function getRemainingUses(string $code): int
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return 0;
        }

        return $voucher->getRemainingUses() ?? PHP_INT_MAX;
    }
}
