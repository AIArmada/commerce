<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Cart\Cart;
use Lorisleiva\Actions\Concerns\AsAction;

final class DetachAffiliateFromCart
{
    use AsAction;

    public function handle(Cart $cart): void
    {
        AffiliateAttribution::query()
            ->where('cart_identifier', $cart->getIdentifier())
            ->where('cart_instance', $cart->instance())
            ->active()
            ->update(['expires_at' => now()]);
    }
}
