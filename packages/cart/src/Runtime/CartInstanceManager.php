<?php

declare(strict_types=1);

namespace AIArmada\Cart\Runtime;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\Cart\Data\CartSnapshotData;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;

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

    public function resolveForSnapshot(CartSnapshotData $snapshot): Cart
    {
        $owner = OwnerTupleParser::fromTypeAndId(
            $snapshot->ownerType,
            $snapshot->ownerId,
        )->toOwnerModel();

        return OwnerContext::withOwner($owner, fn () => $this->resolve($snapshot->instance, $snapshot->identifier));
    }
}
