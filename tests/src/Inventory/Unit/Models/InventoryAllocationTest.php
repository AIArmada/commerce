<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Carbon\CarbonImmutable;

describe('InventoryAllocation', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
        $this->level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
    });

    it('can create allocation', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-123',
            'quantity' => 10,
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation)->toBeInstanceOf(InventoryAllocation::class);
        expect($allocation->cart_id)->toBe('cart-123');
        expect($allocation->quantity)->toBe(10);
    });

    it('location relationship', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation->location)->not->toBeNull();
        expect($allocation->location->id)->toBe($this->location->id);
    });

    it('level relationship', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation->level)->not->toBeNull();
        expect($allocation->level->id)->toBe($this->level->id);
    });

    it('inventoryable relationship', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation->inventoryable)->not->toBeNull();
        expect($allocation->inventoryable->id)->toBe($this->item->id);
    });

    it('scope for cart', function (): void {
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-A',
            'expires_at' => now()->addHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-B',
            'expires_at' => now()->addHour(),
        ]);

        $allocations = InventoryAllocation::forCart('cart-A')->get();

        expect($allocations)->toHaveCount(1);
        expect($allocations->first()->cart_id)->toBe('cart-A');
    });

    it('scope expired', function (): void {
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->subHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-2',
            'expires_at' => CarbonImmutable::now()->addHour(),
        ]);

        $expired = InventoryAllocation::expired()->get();

        expect($expired)->toHaveCount(1);
        expect($expired->first()->cart_id)->toBe('cart-1');
    });

    it('scope active', function (): void {
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->addHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-2',
            'expires_at' => CarbonImmutable::now()->subHour(),
        ]);

        $active = InventoryAllocation::active()->get();

        expect($active)->toHaveCount(1);
        expect($active->first()->cart_id)->toBe('cart-1');
    });

    it('scope at location', function (): void {
        $location2 = InventoryLocation::factory()->create();
        $level2 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
        ]);

        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'level_id' => $level2->id,
            'cart_id' => 'cart-2',
            'expires_at' => now()->addHour(),
        ]);

        $atLocation = InventoryAllocation::atLocation($this->location->id)->get();

        expect($atLocation)->toHaveCount(1);
        expect($atLocation->first()->cart_id)->toBe('cart-1');
    });

    it('is expired returns true when expired', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->subMinutes(5),
        ]);

        expect($allocation->isExpired())->toBeTrue();
    });

    it('is expired returns false when not expired', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->addHour(),
        ]);

        expect($allocation->isExpired())->toBeFalse();
    });

    it('is active returns true when not expired', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->addHour(),
        ]);

        expect($allocation->isActive())->toBeTrue();
    });

    it('is active returns false when expired', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->subMinutes(5),
        ]);

        expect($allocation->isActive())->toBeFalse();
    });

    it('extend updates expires at', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        $allocation->extend(120);

        $allocation->refresh();
        expect($allocation->expires_at->isAfter(now()->addMinutes(110)))->toBeTrue();
    });

    it('casts are correct', function (): void {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'quantity' => 10,
            'expires_at' => CarbonImmutable::now()->addHour(),
        ]);

        expect($allocation->quantity)->toBeInt();
        expect($allocation->expires_at)->toBeInstanceOf(CarbonImmutable::class);
    });
});
