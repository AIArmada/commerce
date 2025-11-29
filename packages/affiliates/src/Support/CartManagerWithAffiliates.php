<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;

final class CartManagerWithAffiliates
{
    public function __construct(
        private CartManager $manager
    ) {}

    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(CartWithAffiliates::class, $method)) {
            $wrapper = new CartWithAffiliates($this->getCurrentCart());

            return $wrapper->{$method}(...$arguments);
        }

        return $this->manager->{$method}(...$arguments);
    }

    public static function fromCartManager(CartManager $manager): self
    {
        return new self($manager);
    }

    public function getCurrentCart(): Cart
    {
        return (new CartWithAffiliates($this->manager->getCurrentCart()))->getCart();
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return (new CartWithAffiliates($this->manager->getCartInstance($name, $identifier)))->getCart();
    }

    // Delegate core methods
    public function instance(): string
    {
        return $this->manager->instance();
    }

    public function setInstance(string $instance): static
    {
        $this->manager->setInstance($instance);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->manager->setIdentifier($identifier);

        return $this;
    }
}
