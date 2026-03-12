<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cashier\Checkout\CartCheckoutBuilder;
use AIArmada\Cashier\GatewayManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Cart manager decorator that adds payment/checkout capabilities.
 *
 * This proxy extends the cart manager with checkout functionality:
 * - `checkout()` - Create a checkout builder for the current cart
 * - `checkoutWithGateway()` - Checkout using specific gateway
 */
final class CartManagerWithPayment implements CartManagerInterface
{
    private CartManagerInterface $cart;

    private function __construct(CartManagerInterface $cart)
    {
        $this->cart = $cart;
    }

    /**
     * Forward any method calls to the underlying cart manager.
     *
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->cart->{$method}(...$arguments);
    }

    public static function fromCartManager(CartManagerInterface $cart): self
    {
        if ($cart instanceof self) {
            return $cart;
        }

        return new self($cart);
    }

    /**
     * Create a checkout builder for the current cart.
     */
    public function checkout(?string $identifier = null, ?string $gateway = null): CartCheckoutBuilder
    {
        $cart = $this->cart->getCurrentCart();

        if ($identifier !== null) {
            $cart = $cart->setIdentifier($identifier);
        }

        /** @var GatewayManager $gatewayManager */
        $gatewayManager = app(GatewayManager::class);

        $gatewayInstance = $gateway
            ? $gatewayManager->gateway($gateway)
            : $gatewayManager->gateway();

        return new CartCheckoutBuilder($cart, $gatewayInstance);
    }

    /**
     * Create a checkout builder with a specific gateway.
     */
    public function checkoutWithGateway(string $gateway, ?string $identifier = null): CartCheckoutBuilder
    {
        return $this->checkout($identifier, $gateway);
    }

    public function getCurrentCart(): Cart
    {
        return $this->cart->getCurrentCart();
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->cart->getCartInstance($name, $identifier);
    }

    public function instance(): string
    {
        return $this->cart->instance();
    }

    public function setInstance(string $name): static
    {
        $this->cart->setInstance($name);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->cart->setIdentifier($identifier);

        return $this;
    }

    public function forgetIdentifier(): static
    {
        $this->cart->forgetIdentifier();

        return $this;
    }

    public function forOwner(Model $owner): static
    {
        return new self($this->cart->forOwner($owner));
    }

    public function getOwnerType(): ?string
    {
        return $this->cart->getOwnerType();
    }

    public function getOwnerId(): string | int | null
    {
        return $this->cart->getOwnerId();
    }

    public function session(?string $sessionKey = null): StorageInterface
    {
        return $this->cart->session($sessionKey);
    }

    public function getById(string $uuid): ?Cart
    {
        return $this->cart->getById($uuid);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return $this->cart->swap($oldIdentifier, $newIdentifier, $instance);
    }
}
