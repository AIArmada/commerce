<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateFromCookie;
use AIArmada\Affiliates\Contracts\AffiliateLookup;
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

        if (app(AffiliateLookup::class)->findAttachedAttribution($this->cart) !== null) {
            return;
        }

        $cookieValue = request()->cookie(config('affiliates.cookies.name', 'affiliate_session'));

        if (! $cookieValue) {
            return;
        }

        app(AttachAffiliateFromCookie::class)->handle($this->cart, $cookieValue);
    }
}
