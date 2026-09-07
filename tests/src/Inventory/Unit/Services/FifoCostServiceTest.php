<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\Costing\FifoCostService;

describe('FifoCostService', function (): void {
    beforeEach(function (): void {
        $this->service = new FifoCostService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    });

    it('add layer', function (): void {
        $layer = $this->service->addLayer(
            $this->item,
            100,
            500,
            $this->location->id,
            null,
            'PO-001',
            now()
        );

        expect($layer)->toBeInstanceOf(InventoryCostLayer::class);
        expect($layer->quantity)->toBe(100);
        expect($layer->remaining_quantity)->toBe(100);
        expect($layer->unit_cost_minor)->toBe(500);
        expect($layer->total_cost_minor)->toBe(50000);
        expect($layer->costing_method)->toBe(CostingMethod::Fifo);
    });

    it('consume from single layer', function (): void {
        $this->service->addLayer($this->item, 100, 500, $this->location->id);

        $result = $this->service->consume($this->item, 30, $this->location->id);

        expect($result['consumed'])->toBe(30);
        expect($result['cost'])->toBe(15000); // 30 * 500
        expect($result['layers'])->toHaveCount(1);
    });

    it('consume across multiple layers fifo', function (): void {
        // First layer - older, lower cost
        $this->service->addLayer($this->item, 50, 400, $this->location->id, null, null, now()->subDays(2));
        // Second layer - newer, higher cost
        $this->service->addLayer($this->item, 50, 600, $this->location->id, null, null, now()->subDay());

        $result = $this->service->consume($this->item, 70, $this->location->id);

        expect($result['consumed'])->toBe(70);
        // FIFO: 50 @ 400 + 20 @ 600 = 20000 + 12000 = 32000
        expect($result['cost'])->toBe(32000);
        expect($result['layers'])->toHaveCount(2);
    });

    it('consume returns partial when insufficient', function (): void {
        $this->service->addLayer($this->item, 30, 500, $this->location->id);

        $result = $this->service->consume($this->item, 50, $this->location->id);

        expect($result['consumed'])->toBe(30);
    });

    it('calculate valuation', function (): void {
        $this->service->addLayer($this->item, 100, 400, $this->location->id);
        $this->service->addLayer($this->item, 50, 600, $this->location->id);

        $valuation = $this->service->calculateValuation($this->item, $this->location->id);

        expect($valuation['quantity'])->toBe(150);
        // 100*400 + 50*600 = 40000 + 30000 = 70000
        expect($valuation['value'])->toBe(70000);
        expect($valuation['layers'])->toBe(2);
    });

    it('estimate cogs', function (): void {
        $this->service->addLayer($this->item, 50, 400, $this->location->id, null, null, now()->subDays(2));
        $this->service->addLayer($this->item, 50, 600, $this->location->id, null, null, now()->subDay());

        // Estimate COGS for 70 units using FIFO
        $cogs = $this->service->estimateCogs($this->item, 70, $this->location->id);

        // FIFO: 50 @ 400 + 20 @ 600 = 32000
        expect($cogs)->toBe(32000);
    });

    it('get active layers', function (): void {
        $this->service->addLayer($this->item, 100, 500, $this->location->id);
        $this->service->addLayer($this->item, 50, 600, $this->location->id);

        $layers = $this->service->getActiveLayers($this->item, $this->location->id);

        expect($layers)->toHaveCount(2);
    });

    it('get oldest layer', function (): void {
        $this->service->addLayer($this->item, 100, 500, $this->location->id, null, null, now()->subDays(2));
        $this->service->addLayer($this->item, 50, 600, $this->location->id, null, null, now());

        $oldest = $this->service->getOldestLayer($this->item, $this->location->id);

        expect($oldest)->not->toBeNull();
        expect($oldest->unit_cost_minor)->toBe(500);
    });

    it('has available quantity true', function (): void {
        $this->service->addLayer($this->item, 100, 500, $this->location->id);

        $hasQuantity = $this->service->hasAvailableQuantity($this->item, 80, $this->location->id);

        expect($hasQuantity)->toBeTrue();
    });

    it('has available quantity false', function (): void {
        $this->service->addLayer($this->item, 50, 500, $this->location->id);

        $hasQuantity = $this->service->hasAvailableQuantity($this->item, 100, $this->location->id);

        expect($hasQuantity)->toBeFalse();
    });

    it('consume updates remaining quantity', function (): void {
        $layer = $this->service->addLayer($this->item, 100, 500, $this->location->id);

        $this->service->consume($this->item, 30, $this->location->id);

        expect($layer->fresh()->remaining_quantity)->toBe(70);
    });
});
