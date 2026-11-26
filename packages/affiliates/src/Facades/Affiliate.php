<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Facades;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord|null findByCode(string $code)
 * @method static \AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord|null findByDefaultVoucherCode(string $voucherCode)
 * @method static \AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord|null findById(string $id)
 */
final class Affiliate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AffiliateDirectory::class;
    }
}
