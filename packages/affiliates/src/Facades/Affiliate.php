<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Facades;

use AIArmada\Affiliates\Contracts\AffiliateLookup;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Affiliates\Models\Affiliate|null findByCode(string $code)
 * @method static \AIArmada\Affiliates\Models\Affiliate|null findByDefaultVoucherCode(string $voucherCode)
 * @method static \AIArmada\Affiliates\Models\Affiliate|null findById(string $id)
 */
final class Affiliate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AffiliateLookup::class;
    }
}
