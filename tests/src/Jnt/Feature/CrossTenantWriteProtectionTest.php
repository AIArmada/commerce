<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntOrderItem;
use AIArmada\Jnt\Models\JntOrderParcel;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', false);
    config()->set('jnt.owner.auto_assign_on_create', true);
});

it('prevents cross-tenant writes via order_id on child models', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-ct@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-ct@example.com',
        'password' => 'secret',
    ]);

    $orderA = OwnerContext::withOwner($ownerA, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-CT-A',
        'customer_code' => 'CUST',
    ]));

    $orderB = OwnerContext::withOwner($ownerB, fn () => JntOrder::query()->create([
        'order_id' => 'ORD-CT-B',
        'customer_code' => 'CUST',
    ]));

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(fn (): JntOrderItem => JntOrderItem::query()->create([
        'order_id' => $orderB->id,
        'name' => 'Widget',
        'quantity' => 1,
        'weight_grams' => 100,
        'unit_price' => '10.00',
        'currency' => 'MYR',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): JntOrderParcel => JntOrderParcel::query()->create([
        'order_id' => $orderB->id,
        'sequence' => 1,
        'tracking_number' => 'TRK-B-CT-1',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): JntTrackingEvent => JntTrackingEvent::query()->create([
        'order_id' => $orderB->id,
        'tracking_number' => 'TRK-B-CT-2',
        'scan_type_code' => '100',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): JntWebhookLog => JntWebhookLog::query()->create([
        'order_id' => $orderB->id,
        'tracking_number' => 'TRK-B-CT-3',
    ]))->toThrow(InvalidArgumentException::class);

    $itemForOrderA = JntOrderItem::query()->create([
        'order_id' => $orderA->id,
        'name' => 'Widget',
        'quantity' => 1,
        'weight_grams' => 100,
        'unit_price' => '10.00',
        'currency' => 'MYR',
    ]);

    expect($itemForOrderA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($itemForOrderA->owner_id)->toBe($ownerA->getKey());

    $parcelForOrderA = JntOrderParcel::query()->create([
        'order_id' => $orderA->id,
        'sequence' => 1,
        'tracking_number' => 'TRK-A-CT-1',
    ]);

    expect($parcelForOrderA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($parcelForOrderA->owner_id)->toBe($ownerA->getKey());

    $eventForOrderA = JntTrackingEvent::query()->create([
        'order_id' => $orderA->id,
        'tracking_number' => 'TRK-A-CT-2',
        'scan_type_code' => '100',
    ]);

    expect($eventForOrderA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($eventForOrderA->owner_id)->toBe($ownerA->getKey());

    $logForOrderA = JntWebhookLog::query()->create([
        'order_id' => $orderA->id,
        'tracking_number' => 'TRK-A-CT-3',
    ]);

    expect($logForOrderA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($logForOrderA->owner_id)->toBe($ownerA->getKey());
});
