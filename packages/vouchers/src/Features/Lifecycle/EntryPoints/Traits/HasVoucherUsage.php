<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\EntryPoints\Traits;

use AIArmada\Vouchers\Features\Lifecycle\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait HasVoucherUsage
{
    /**
     * Get the voucher usages redeemed by this model.
     *
     * @return MorphMany<VoucherUsage, $this>
     */
    public function voucherUsages(): MorphMany
    {
        return $this->morphMany(VoucherUsage::class, 'redeemed_by');
    }
}
