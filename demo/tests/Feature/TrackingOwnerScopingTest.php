<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Models\JntOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only shipments for the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        JntOrder::create([
            'order_id' => 'ORD-OWNER-A-0001',
            'tracking_number' => 'JT111111111111',
            'customer_code' => 'DEMO-A',
            'action_type' => '2',
            'status' => 'PICKUP',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'receiver' => ['city' => 'Kuala Lumpur'],
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerB): void {
        JntOrder::create([
            'order_id' => 'ORD-OWNER-B-0001',
            'tracking_number' => 'JT222222222222',
            'customer_code' => 'DEMO-B',
            'action_type' => '2',
            'status' => 'PICKUP',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'receiver' => ['city' => 'Penang'],
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        $this->get('/tracking')
            ->assertOk()
            ->assertSee('JT111111111111')
            ->assertDontSee('JT222222222222');

        $this->get('/tracking?tracking_number=JT222222222222')
            ->assertOk()
            ->assertSee('No shipment found');
    });
});

it('can search shipments by order id within the current owner context', function (): void {
    $ownerA = User::factory()->create();

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        JntOrder::create([
            'order_id' => 'ORD-SEARCH-0001',
            'tracking_number' => 'JT333333333333',
            'customer_code' => 'DEMO-A',
            'action_type' => '2',
            'status' => 'PICKUP',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        $this->get('/tracking?tracking_number=ORD-SEARCH-0001')
            ->assertOk()
            ->assertSee('JT333333333333');
    });
});
