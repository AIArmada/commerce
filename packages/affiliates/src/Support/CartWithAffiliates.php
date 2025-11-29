<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Traits\HasAffiliates;
use AIArmada\Cart\Cart;

final class CartWithAffiliates
{
    use HasAffiliates;

    public function __construct(private readonly Cart $cart) {}

    public function __call(string $method, array $arguments): mixed
    {
        return $this->cart->{$method}(...$arguments);
    }

    public function getCart(): Cart
    {
        $this->hydrateAffiliateFromCookie();

        return $this->cart;
    }

    private function hydrateAffiliateFromCookie(): void
    {
        if (! config('affiliates.cookies.enabled', true)) {
            return;
        }

        if (! app()->bound('request')) {
            return;
        }

        $metadataKey = config('affiliates.cart.metadata_key', 'affiliate');

        if ($this->cart->hasMetadata($metadataKey)) {
            return;
        }

        $cookieValue = request()->cookie(config('affiliates.cookies.name', 'affiliate_session'));

        if (! $cookieValue) {
            return;
        }

        app(AffiliateService::class)->attachAffiliateFromCookie($this->cart, $cookieValue);
    }
}
