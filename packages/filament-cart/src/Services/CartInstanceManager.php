<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;

class CartInstanceManager
{
    public function __construct(private readonly RulesFactoryInterface $rulesFactory) {}

    public function resolve(string $instance, string $identifier): Cart
    {
        $cart = CartFacade::getCartInstance($instance, $identifier);

        return $cart->withRulesFactory($this->rulesFactory);
    }

    public function prepare(Cart $cart): Cart
    {
        return $cart->withRulesFactory($this->rulesFactory);
    }

    public function resolveForSnapshot(CartSnapshot $snapshot): Cart
    {
        $owner = OwnerContext::fromTypeAndId($snapshot->owner_type, $snapshot->owner_id);

        return OwnerContext::withOwner($owner, fn () => $this->resolve($snapshot->instance, $snapshot->identifier));
    }
}
