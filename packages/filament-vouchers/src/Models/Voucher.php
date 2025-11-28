<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Models;

use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Models\Voucher as BaseVoucher;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Extended Voucher model with Filament-specific attributes.
 *
 * This model extends the base Voucher to add Filament-specific functionality
 * like owner display name resolution using the OwnerTypeRegistry.
 */
final class Voucher extends BaseVoucher
{
    /**
     * Provides a human-readable representation of the polymorphic owner using
     * the configured owner registry.
     *
     * Overrides the base model's owner_display_name to use Filament's
     * OwnerTypeRegistry for more customizable display formatting.
     */
    protected function ownerDisplayName(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $owner = $this->owner;

                if (! $owner) {
                    return null;
                }

                return app(OwnerTypeRegistry::class)->resolveDisplayLabel($owner);
            }
        );
    }
}
