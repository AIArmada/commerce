<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Models\Voucher;
use Closure;
use Illuminate\Database\Eloquent\Model;

interface VoucherLookup
{
    /** @param Closure(): ?VoucherData $resolver */
    public function remember(string $code, Model | OwnerScopeIdentifiable | null $owner, bool $includeGlobal, Closure $resolver): ?VoucherData;

    public function forget(string $code, Model | OwnerScopeIdentifiable | null $owner): void;

    public function forgetForVoucher(Voucher $voucher, ?string $code = null): void;
}
