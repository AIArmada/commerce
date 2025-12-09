<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\Affiliates\Support\CartManagerWithAffiliates;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;
use Illuminate\Contracts\Foundation\Application;

final class CartIntegrationRegistrar
{
    public function __construct(private readonly Application $app) {}

    public function register(): void
    {
        if (! class_exists(CartManager::class)) {
            return;
        }

        $this->app->extend('cart', function (CartManagerInterface $manager, Application $app) {
            if ($manager instanceof CartManagerWithAffiliates) {
                return $manager;
            }

            $proxy = CartManagerWithAffiliates::fromCartManager($manager);

            $app->instance(CartManager::class, $proxy);
            $app->instance(CartManagerInterface::class, $proxy);

            if (class_exists(Cart::class)) {
                Cart::clearResolvedInstance('cart');
            }

            return $proxy;
        });
    }
}
