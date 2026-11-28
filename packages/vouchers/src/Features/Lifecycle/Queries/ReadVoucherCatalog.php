<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\ResolvesVoucherAssignee;
use AIArmada\Vouchers\Runtime\VoucherLookup;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;

final class ReadVoucherCatalog implements VoucherCatalog
{
    use QueriesVouchers;
    use ResolvesVoucherAssignee;

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

    public function resolveId(string $code, ?string $voucherId = null): ?string
    {
        return $this->voucherIdentityQuery($code, $voucherId)->select('id')->first()?->id;
    }

    public function lockForWalletClaim(string $code, ?string $voucherId, string $holderType, string $holderId): string
    {
        $voucher = $this->voucherIdentityQuery($code, $voucherId)->lockForUpdate()->first();

        if ($voucher === null) {
            throw VoucherNotFoundException::withCode($code);
        }

        $assignee = $this->resolveAssignee($voucher);

        if ($assignee !== null && ($holderType !== $assignee['type'] || $holderId !== $assignee['id'])) {
            throw InvalidVoucherException::assignedToAnother($voucher->code);
        }

        return $voucher->id;
    }

    public function invalidate(string $code): void
    {
        $this->lookupCache->forget($this->normalizeCode($code), $this->resolveOwner());
    }

    public function getRemainingUses(string $code): int
    {
        $voucher = $this->voucherQuery(withUsageCount: true)
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return 0;
        }

        return $voucher->getRemainingUses() ?? PHP_INT_MAX;
    }
}
