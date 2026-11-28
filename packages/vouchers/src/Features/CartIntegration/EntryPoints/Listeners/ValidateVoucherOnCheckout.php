<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\EntryPoints\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Vouchers\Features\CartIntegration\Actions\RevalidateCartVouchers;

final readonly class ValidateVoucherOnCheckout
{
    public function __construct(private RevalidateCartVouchers $revalidateCartVouchers) {}

    public function handle(object $event): void
    {
        $cart = $this->extractCart($event);

        if ($cart !== null) {
            $this->revalidateCartVouchers->handle($cart);
        }
    }

    private function extractCart(object $event): ?Cart
    {
        if (property_exists($event, 'cart') && $event->cart instanceof Cart) {
            return $event->cart;
        }

        if (! property_exists($event, 'session') || ! is_object($event->session)) {
            return null;
        }

        $session = $event->session;
        $cartId = method_exists($session, 'getAttribute')
            ? $session->getAttribute('cart_id')
            : (property_exists($session, 'cart_id') ? $session->cart_id : null);

        if (! is_string($cartId) || mb_trim($cartId) === '' || ! app()->bound(CartManagerInterface::class)) {
            return null;
        }

        return app(CartManagerInterface::class)->getById($cartId);
    }
}
