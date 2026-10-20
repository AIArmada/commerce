<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;

beforeEach(function (): void {
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('cart.owner.auto_assign_on_create', true);

    $this->ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'ambient-owner-a@example.com',
        'password' => 'secret',
    ]);

    $this->ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'ambient-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($this->ownerA));
});

describe('owner-scoped ambient cart manager', function (): void {
    it('resolves ambient carts for the current owner and restores the previous owner state', function (): void {
        // Resolve the facade while owner A is ambient. This is the historical
        // bug window: the manager used to retain A's storage for the lifecycle.
        Cart::add('item-a', 'Item A', 10.00, 1);

        $identifier = Cart::getIdentifier();
        $idA = Cart::getId();

        expect($idA)->not->toBeNull();

        OwnerContext::withOwner($this->ownerB, function () use ($identifier): void {
            // Same identifier, different owner: owner B sees its own empty cart.
            expect(Cart::getItems())->toHaveCount(0)
                ->and(Cart::getIdentifier())->toBe($identifier);

            Cart::add('item-b', 'Item B', 5.00, 1);

            expect(Cart::getItems())->toHaveCount(1)
                ->and(Cart::get('item-b'))->not->toBeNull()
                ->and(Cart::get('item-a'))->toBeNull()
                ->and(Cart::getId())->not->toBeNull();
        });

        // Back in owner A, the original cart is intact and not the shared state.
        expect(Cart::getItems())->toHaveCount(1)
            ->and(Cart::get('item-a'))->not->toBeNull()
            ->and(Cart::get('item-b'))->toBeNull()
            ->and(Cart::getId())->toBe($idA);
    });

    it('keeps an explicit global cart distinct from the ambient owner cart', function (): void {
        Cart::add('item-a', 'Item A', 10.00, 1);

        $idA = Cart::getId();

        $idGlobal = OwnerContext::withOwner(null, function (): ?string {
            expect(Cart::getItems())->toHaveCount(0);

            Cart::add('item-global', 'Global Item', 1.00, 1);

            expect(Cart::getItems())->toHaveCount(1);

            return Cart::getId();
        });

        expect($idGlobal)->not->toBeNull()
            ->and($idGlobal)->not->toBe($idA);

        expect(Cart::getItems())->toHaveCount(1)
            ->and(Cart::get('item-a'))->not->toBeNull()
            ->and(Cart::get('item-global'))->toBeNull()
            ->and(Cart::getId())->toBe($idA);
    });

    it('partitions instance, identifier overrides, and getById caches by owner', function (): void {
        Cart::setInstance('wishlist');
        Cart::setIdentifier('shared-identifier');
        Cart::add('wishlist-item', 'Wishlist Item', 20.00, 1);

        $idA = Cart::getId();
        $firstLookup = Cart::getById($idA);

        expect($idA)->not->toBeNull()
            ->and($firstLookup)->not->toBeNull();

        [$instance, $identifier, $lookup] = OwnerContext::withOwner($this->ownerB, function () use ($idA): array {
            expect(Cart::getItems())->toHaveCount(0);

            return [
                Cart::instance(),
                Cart::getIdentifier(),
                Cart::getById($idA),
            ];
        });

        expect($instance)->toBe('default')
            ->and($identifier)->not->toBe('shared-identifier')
            ->and($lookup)->toBeNull();

        expect(Cart::instance())->toBe('wishlist')
            ->and(Cart::getIdentifier())->toBe('shared-identifier')
            ->and(Cart::getItems())->toHaveCount(1)
            ->and(Cart::getById($idA))->toBe($firstLookup);
    });

    it('isolates the container-resolved CartManagerInterface surface', function (): void {
        $manager = app(CartManagerInterface::class);

        $cartA = $manager->getCurrentCart();
        $cartA->add('item-a', 'Item A', 10.00, 1);
        $idA = $cartA->getId();

        OwnerContext::withOwner($this->ownerB, function () use ($manager, $cartA): void {
            $ambientCart = $manager->getCurrentCart();

            expect($ambientCart)->not->toBe($cartA)
                ->and($ambientCart->getItems())->toHaveCount(0);

            $ambientCart->add('item-b', 'Item B', 5.00, 1);

            // The concrete cart obtained earlier keeps its explicitly selected owner.
            expect($cartA->getItems())->toHaveCount(1)
                ->and($cartA->get('item-a'))->not->toBeNull()
                ->and($cartA->get('item-b'))->toBeNull();
        });

        expect($manager->getCurrentCart()->getItems())->toHaveCount(1)
            ->and($manager->getCurrentCart()->get('item-a'))->not->toBeNull()
            ->and($cartA->getId())->toBe($idA);
    });

    it('fails closed without a resolvable owner and still honors explicit global context', function (): void {
        Cart::add('item-a', 'Item A', 10.00, 1);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

        expect(fn () => Cart::getItems())->toThrow(RuntimeException::class);

        $globalItems = OwnerContext::withOwner(null, fn () => Cart::getItems());

        expect($globalItems)->toHaveCount(0);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($this->ownerA));

        expect(Cart::getItems())->toHaveCount(1);
    });

    it('syncs and resolves snapshots under the source cart owner', function (): void {
        config()->set('cart.snapshots.synchronization.queue_connection', 'sync');
        config()->set('cart.snapshots.synchronization.queue_sync', true);

        // Resolve the facade under owner A first; the snapshot job for owner B
        // must still sync B's cart, not the retained A cart.
        Cart::add('item-a', 'Item A', 10.00, 1);

        $identifier = Cart::getIdentifier();

        OwnerContext::withOwner($this->ownerB, function (): void {
            Cart::add('item-b', 'Item B', 5.00, 1);
        });

        $snapshotA = OwnerContext::withOwner($this->ownerA, fn (): ?CartSnapshot => CartSnapshot::query()
            ->forOwner($this->ownerA)
            ->where('identifier', $identifier)
            ->where('instance', 'default')
            ->first());

        $snapshotB = OwnerContext::withOwner($this->ownerB, fn (): ?CartSnapshot => CartSnapshot::query()
            ->forOwner($this->ownerB)
            ->where('identifier', $identifier)
            ->where('instance', 'default')
            ->first());

        expect($snapshotA)->not->toBeNull()
            ->and($snapshotB)->not->toBeNull()
            ->and((string) $snapshotA?->owner_id)->toBe((string) $this->ownerA->getKey())
            ->and((string) $snapshotB?->owner_id)->toBe((string) $this->ownerB->getKey());

        // Snapshot resolution pins the cart to the snapshot owner even inside
        // another owner's context.
        $resolvedB = OwnerContext::withOwner($this->ownerB, fn () => $snapshotB?->getCartInstance());
        $resolvedA = OwnerContext::withOwner($this->ownerA, fn () => $snapshotA?->getCartInstance());

        expect($resolvedB)->not->toBeNull()
            ->and($resolvedB?->storage()->getOwnerType())->toBe($this->ownerB->getMorphClass())
            ->and($resolvedB?->get('item-b'))->not->toBeNull()
            ->and($resolvedB?->get('item-a'))->toBeNull();

        expect($resolvedA)->not->toBeNull()
            ->and($resolvedA?->storage()->getOwnerType())->toBe($this->ownerA->getMorphClass())
            ->and($resolvedA?->get('item-a'))->not->toBeNull()
            ->and($resolvedA?->get('item-b'))->toBeNull();
    });
});
