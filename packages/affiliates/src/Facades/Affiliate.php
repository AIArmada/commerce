<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Affiliates\Models\Affiliate|null findByCode(string $code)
 * @method static \AIArmada\Affiliates\Data\AffiliateAttributionData|null attachToCartByCode(string $code, \AIArmada\Cart\Cart $cart, array $context = [])
 */
final class Affiliate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'affiliates';
    }
}
