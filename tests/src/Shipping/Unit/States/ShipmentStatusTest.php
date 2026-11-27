<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\TrackingStatus;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\States\Cancelled;
use AIArmada\Shipping\Features\Shipments\States\Delivered;
use AIArmada\Shipping\Features\Shipments\States\Draft;
use AIArmada\Shipping\Features\Shipments\States\InTransit;
use AIArmada\Shipping\Features\Shipments\States\Pending;
use AIArmada\Shipping\Features\Shipments\States\ShipmentStatus;
use AIArmada\Shipping\Features\Shipments\States\Shipped;

it('uses the state machine as the canonical shipment status registry', function (): void {
    expect(ShipmentStatus::normalize(Draft::class))->toBe('draft')
        ->and(ShipmentStatus::normalize(Pending::class))->toBe('pending')
        ->and(ShipmentStatus::resolveStateClassFor('shipped'))->toBe(Shipped::class)
        ->and(ShipmentStatus::options())->toMatchArray([
            'draft' => 'Draft',
            'pending' => 'Pending',
            'delivered' => 'Delivered',
        ]);
});

it('keeps status behavior on canonical state instances', function (): void {
    $shipment = new Shipment;
    $pending = new Pending($shipment);
    $delivered = new Delivered($shipment);

    expect($pending->isPending())->toBeTrue()
        ->and($pending->isCancellable())->toBeTrue()
        ->and($pending->toTrackingStatus())->toBe(TrackingStatus::AwaitingPickup)
        ->and($delivered->isDelivered())->toBeTrue()
        ->and($delivered->isTerminal())->toBeTrue()
        ->and($delivered->transitionableStates())->toBeEmpty();
});

it('normalizes state instances and terminal statuses without a parallel enum', function (): void {
    $shipment = new Shipment;
    $inTransit = new InTransit($shipment);
    $cancelled = new Cancelled($shipment);

    expect(ShipmentStatus::normalize($inTransit))->toBe('in_transit')
        ->and($cancelled->isTerminal())->toBeTrue()
        ->and($cancelled->transitionableStates())->toBeEmpty();
});
