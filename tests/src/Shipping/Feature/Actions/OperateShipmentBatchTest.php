<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Shipping\Features\Shipments\Api\BulkShipmentOperations;
use AIArmada\Shipping\Features\Shipments\Api\ManageManualShipment;
use AIArmada\Shipping\Features\Shipments\Api\ShipmentOperations;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use Illuminate\Support\Facades\Gate;

it('executes authorized shipment identifiers once and counts denied records', function (): void {
    config(['shipping.features.owner.enabled' => false]);
    $manual = app(ManageManualShipment::class);
    $allowed = $manual->create(['reference' => 'BATCH-ALLOWED', 'carrier_code' => 'manual', 'origin_address' => [], 'destination_address' => []]);
    $denied = $manual->create(['reference' => 'BATCH-DENIED', 'carrier_code' => 'manual', 'origin_address' => [], 'destination_address' => []]);

    $this->actingAs(User::query()->create(['name' => 'Batch actor', 'email' => 'batch-actor@example.com', 'password' => 'secret']));
    Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'ship' && ($arguments[0] ?? null) instanceof Shipment ? $arguments[0]->getKey() === $allowed : null);

    $operations = Mockery::mock(ShipmentOperations::class);
    $operations->shouldReceive('ship')->once()->with($allowed);
    app()->instance(ShipmentOperations::class, $operations);

    $result = app(BulkShipmentOperations::class)->ship([$allowed, $denied, $allowed]);

    expect($result->succeeded)->toBe(1)
        ->and($result->failed)->toBe(1);
});
