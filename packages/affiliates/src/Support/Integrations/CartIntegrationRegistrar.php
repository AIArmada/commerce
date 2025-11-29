<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\Affiliates\Support\CartManagerWithAffiliates;
use AIArmada\Cart\CartManager;
use Illuminate\Contracts\Foundation\Application;

final class CartIntegrationRegistrar
{
    public function __construct(private readonly Application $app) {}

    public function register(): void
    {
        if (! class_exists(CartManager::class)) {
            return;
        }

        $this->app->extend('cart', function (CartManager $manager, Application $app) {
            if ($manager instanceof CartManagerWithAffiliates) {
                return $manager;
            }

            $proxy = CartManagerWithAffiliates::fromCartManager($manager);

            $app->instance(CartManager::class, $proxy);

            if (class_exists(\AIArmada\Cart\Facades\Cart::class)) {
                \AIArmada\Cart\Facades\Cart::clearResolvedInstance('cart');
            }

            return $proxy;
        });
    }
}
