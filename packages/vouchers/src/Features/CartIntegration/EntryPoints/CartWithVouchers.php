<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\EntryPoints;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\Traits\InteractsWithVouchers;

/** Wraps a cart with voucher operations. */
class CartWithVouchers
{
    use InteractsWithVouchers;

    private Cart $cart;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        VoucherRulesFactory::attachTo($this->cart);
    }

    /**
     * Delegate all other method calls to the underlying cart
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->cart->{$method}(...$arguments);
    }

    /**
     * Get the underlying cart instance
     */
    public function getCart(): Cart
    {
        return $this->cart;
    }
}
