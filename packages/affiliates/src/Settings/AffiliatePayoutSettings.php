<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Settings;

use Spatie\LaravelSettings\Settings;

class AffiliatePayoutSettings extends Settings
{
    /**
     * Global payout minimum in minor units, applied in each balance currency.
     */
    public int $minimumAmount;

    /**
     * Per-currency payout minimums in minor units, e.g. ['USD' => 10000].
     */
    public array $minimumAmountsByCurrency;

    public static function group(): string
    {
        return 'affiliate-payouts';
    }
}
