<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Settings;

use Spatie\LaravelSettings\Settings;

class AffiliateCommissionSettings extends Settings
{
    public bool $multi_level_enabled = false;

    /** @var array<int, float> */
    public array $multi_level_rates = [0.1, 0.05];

    public static function group(): string
    {
        return 'affiliate-commissions';
    }
}
