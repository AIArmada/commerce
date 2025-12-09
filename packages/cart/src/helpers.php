<?php

declare(strict_types=1);
use AIArmada\Cart\Cart;

if (! function_exists('cart')) {
    /**
     * Get the Cart instance.
     */
    function cart(?string $instance = null): Cart
    {
        $manager = app('cart');

        if ($instance === null) {
            return $manager->getCurrentCart();
        }

        return $manager->getCartInstance($instance);
    }
}
