<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Models;

use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Models\Voucher as BaseVoucher;

/**
 * Extended Voucher model with Filament-specific attributes.
 */
final class Voucher extends BaseVoucher
{
    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        return app(OwnerTypeRegistry::class)->resolveDisplayLabel($owner);
    }
}
