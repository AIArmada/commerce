<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Features\Orders\Actions\SyncOrderTracking;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Orders\Queries\FindNotificationOrder;
use AIArmada\Jnt\Support\InheritedOwnerGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', true);
    config()->set('jnt.owner.auto_assign_on_create', true);
});

it('requires an owner context for owner-scoped reads when the resolver returns null', function (): void {
    config()->set('jnt.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $orderOwned = OwnerContext::withOwner($ownerA, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-A',
        'customer_code' => 'CUST',
    ]));

    $orderGlobal = OwnerContext::withOwner(null, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-GLOBAL',
        'customer_code' => 'CUST',
    ]));

    // Corrupt record (owner_type set but owner_id null) cannot be created via Eloquent
    // as the guard rejects mismatched nulls; insert raw to test scope exclusion logic.
    $corruptId = (string) str()->uuid();
    DB::table((new JntOrder)->getTable())->insert([
        'id' => $corruptId,
        'order_id' => 'ORD-CORRUPT',
        'customer_code' => 'CUST',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $orderCorrupt = JntOrder::query()->withoutOwnerScope()->find($corruptId);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    expect(fn () => JntOrder::query()->forOwner()->pluck('id')->all())
        ->toThrow(RuntimeException::class);
});

it('returns strict global-only rows only inside explicit global context', function (): void {
    config()->set('jnt.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a2@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-A2',
        'customer_code' => 'CUST',
    ]));

    $orderGlobal = OwnerContext::withOwner(null, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-GLOBAL2',
        'customer_code' => 'CUST',
    ]));

    $corruptId = (string) str()->uuid();
    DB::table((new JntOrder)->getTable())->insert([
        'id' => $corruptId,
        'order_id' => 'ORD-CORRUPT2',
        'customer_code' => 'CUST',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $orderCorrupt = JntOrder::query()->withoutOwnerScope()->find($corruptId);

    $ids = OwnerContext::withOwner(null, fn () => JntOrder::query()->forOwner()->pluck('id')->all());

    expect($ids)
        ->toContain($orderGlobal->id)
        ->not->toContain($orderCorrupt?->id);
});

it('auto-assigns owner on create when enabled', function (): void {
    config()->set('jnt.owner.auto_assign_on_create', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a3@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-A3',
        'customer_code' => 'CUST',
    ]);

    expect($order->owner_type)->toBe($ownerA->getMorphClass())
        ->and($order->owner_id)->toBe($ownerA->getKey());
});

it('fails closed for orphaned owners at tracking trust boundaries', function (): void {
    config()->set('jnt.owner.enabled', true);

    $owner = User::query()->create([
        'name' => 'Deleted Owner',
        'email' => 'deleted-owner@example.com',
        'password' => 'secret',
    ]);

    $order = OwnerContext::withOwner($owner, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-ORPHAN-OWNER',
        'tracking_number' => 'TRK-ORPHAN-OWNER',
        'customer_code' => 'CUST',
    ]));

    $ownerId = (string) $owner->getKey();
    $owner->delete();

    $event = new JntOrderStatusChanged(
        (string) $order->getKey(),
        $order->order_id,
        $order->tracking_number,
        $order->owner_type,
        $ownerId,
        TrackingStatus::InTransit,
    );

    expect($event->owner())->toBeNull()
        ->and(app(FindNotificationOrder::class)->find((string) $order->getKey(), $order->owner_type, $ownerId))->toBeNull();

    expect(fn () => OwnerContext::withOwner($order->owner_type === null ? null : $owner, fn () => app(SyncOrderTracking::class)->syncOrderTracking($order)))
        ->toThrow(AuthorizationException::class);
});

it('requires an explicit global context for inherited global children', function (): void {
    config()->set('jnt.owner.enabled', true);

    expect(fn () => InheritedOwnerGuard::assertMatchesContext(null, null, JntOrder::class))
        ->toThrow(InvalidArgumentException::class);

    OwnerContext::withOwner(null, function (): void {
        InheritedOwnerGuard::assertMatchesContext(null, null, JntOrder::class);
    });

    expect(true)->toBeTrue();
});
