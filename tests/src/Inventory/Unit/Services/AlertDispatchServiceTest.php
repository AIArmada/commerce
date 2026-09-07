<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\Stock\AlertDispatchService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

describe('AlertDispatchService', function (): void {
    beforeEach(function (): void {
        $this->service = new AlertDispatchService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    });

    it('can register notification', function (): void {
        $result = $this->service->registerNotification(AlertStatus::LowStock, 'App\Notifications\LowStockNotification');

        expect($result)->toBeInstanceOf(AlertDispatchService::class);
    });

    it('can register notifiable', function (): void {
        $notifiable = new AnonymousNotifiable;
        $result = $this->service->registerNotifiable('admin', $notifiable);

        expect($result)->toBeInstanceOf(AlertDispatchService::class);
    });

    it('dispatch alert does nothing when notifications disabled', function (): void {
        config(['inventory.events.low_inventory' => false]);

        Notification::fake();

        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $this->service->dispatchAlert($level, AlertStatus::LowStock);

        Notification::assertNothingSent();
    });

    it('dispatch alert does nothing when no notification class', function (): void {
        config(['inventory.events.low_inventory' => true]);

        Notification::fake();

        $notifiable = new AnonymousNotifiable;
        $this->service->registerNotifiable('admin', $notifiable);

        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        // No notification class registered
        $this->service->dispatchAlert($level, AlertStatus::LowStock);

        Notification::assertNothingSent();
    });

    it('dispatch bulk alerts', function (): void {
        config(['inventory.events.low_inventory' => true]);

        $level1 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $location2 = InventoryLocation::factory()->create();
        $level2 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'alert_status' => AlertStatus::OutOfStock->value,
        ]);

        $location3 = InventoryLocation::factory()->create();
        $level3 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location3->id,
            'alert_status' => AlertStatus::None->value,
        ]);

        $count = $this->service->dispatchBulkAlerts([$level1, $level2, $level3]);

        expect($count)->toBe(2);
    });

    it('get alert summary', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $location2 = InventoryLocation::factory()->create();
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $location3 = InventoryLocation::factory()->create();
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location3->id,
            'alert_status' => AlertStatus::OutOfStock->value,
        ]);

        $summary = $this->service->getAlertSummary();

        expect($summary)->toHaveKey('low_stock');
        expect($summary['low_stock'])->toBe(2);
        expect($summary)->toHaveKey('out_of_stock');
        expect($summary['out_of_stock'])->toBe(1);
    });

    it('get critical alerts', function (): void {
        // Create critical alert
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::OutOfStock->value,
            'last_alert_at' => now(),
        ]);

        // Create non-critical alert
        $location2 = InventoryLocation::factory()->create();
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'alert_status' => AlertStatus::None->value,
        ]);

        $criticals = $this->service->getCriticalAlerts();

        expect($criticals)->toHaveCount(1);
    });

    it('acknowledge alert', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $this->service->acknowledgeAlert($level, 'Acknowledged by admin');

        $level->refresh();
        expect($level->metadata['acknowledged_note'])->toBe('Acknowledged by admin');
        expect($level->metadata)->toHaveKey('last_acknowledged_at');
    });

    it('clear alert', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
            'last_alert_at' => now(),
        ]);

        $this->service->clearAlert($level);

        $level->refresh();
        expect($level->alert_status)->toBe(AlertStatus::None->value);
        expect($level->last_alert_at)->toBeNull();
    });
});
