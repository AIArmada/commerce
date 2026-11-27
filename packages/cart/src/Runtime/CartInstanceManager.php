<?php

declare(strict_types=1);

namespace AIArmada\Cart\Runtime;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Data\CartSnapshotData;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

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
        $this->assertSnapshotOwner($snapshot);

        return $this->resolve($snapshot->instance, $snapshot->identifier);
    }

    private function assertSnapshotOwner(CartSnapshotData $snapshot): void
    {
        if (! (bool) config('cart.owner.enabled', false)) {
            return;
        }

        $ambient = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $ambient,
            'Cart snapshots require an owner context or explicit global context.',
        );

        $snapshotOwner = OwnerContext::fromTypeAndId($snapshot->ownerType, $snapshot->ownerId);

        if (! $this->sameOwner($ambient, $snapshotOwner)) {
            throw new AuthorizationException('Cart snapshot is not accessible in the current owner scope.');
        }
    }

    private function sameOwner(?Model $left, ?Model $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->getMorphClass() === $right->getMorphClass()
            && (string) $left->getKey() === (string) $right->getKey();
    }
}
