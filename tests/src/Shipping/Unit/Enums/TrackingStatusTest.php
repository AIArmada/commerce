<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\TrackingStatus;

// ============================================
// TrackingStatus Enum Tests
// ============================================

it('has all expected tracking statuses', function (): void {
    $statuses = TrackingStatus::cases();

    expect($statuses)->toHaveCount(23);
});

it('returns readable labels for tracking statuses', function (): void {
    expect(TrackingStatus::LabelCreated->getLabel())->toBe('Label Created');
    expect(TrackingStatus::InTransit->getLabel())->toBe('In Transit');
    expect(TrackingStatus::OutForDelivery->getLabel())->toBe('Out for Delivery');
    expect(TrackingStatus::Delivered->getLabel())->toBe('Delivered');
    expect(TrackingStatus::DeliveryAttemptFailed->getLabel())->toBe('Delivery Attempt Failed');
    expect(TrackingStatus::Lost->getLabel())->toBe('Lost');
});

it('returns appropriate colors for tracking statuses', function (): void {
    expect(TrackingStatus::LabelCreated->getColor())->toBe('gray');
    expect(TrackingStatus::InTransit->getColor())->toBe('blue');
    expect(TrackingStatus::OutForDelivery->getColor())->toBe('indigo');
    expect(TrackingStatus::Delivered->getColor())->toBe('green');
    expect(TrackingStatus::Lost->getColor())->toBe('red');
});

it('correctly identifies terminal statuses', function (): void {
    expect(TrackingStatus::Delivered->isTerminal())->toBeTrue();
    expect(TrackingStatus::ReturnDelivered->isTerminal())->toBeTrue();
    expect(TrackingStatus::Lost->isTerminal())->toBeTrue();
    expect(TrackingStatus::InTransit->isTerminal())->toBeFalse();
    expect(TrackingStatus::OutForDelivery->isTerminal())->toBeFalse();
});

it('correctly identifies exception statuses', function (): void {
    expect(TrackingStatus::Lost->isException())->toBeTrue();
    expect(TrackingStatus::DeliveryAttemptFailed->isException())->toBeTrue();
    expect(TrackingStatus::Damaged->isException())->toBeTrue();
    expect(TrackingStatus::Delivered->isException())->toBeFalse();
    expect(TrackingStatus::InTransit->isException())->toBeFalse();
});

it('returns appropriate icons for statuses', function (): void {
    expect(TrackingStatus::LabelCreated->getIcon())->toBe('heroicon-o-clock');
    expect(TrackingStatus::Delivered->getIcon())->toBe('heroicon-o-check-circle');
    expect(TrackingStatus::Lost->getIcon())->toBe('heroicon-o-exclamation-triangle');
});

it('returns correct category for statuses', function (): void {
    expect(TrackingStatus::LabelCreated->getCategory())->toBe('pre_shipment');
    expect(TrackingStatus::PickedUp->getCategory())->toBe('pre_shipment');
    expect(TrackingStatus::InTransit->getCategory())->toBe('in_transit');
    expect(TrackingStatus::OutForDelivery->getCategory())->toBe('out_for_delivery');
    expect(TrackingStatus::Delivered->getCategory())->toBe('delivered');
    expect(TrackingStatus::Lost->getCategory())->toBe('exception');
    expect(TrackingStatus::ReturnToSender->getCategory())->toBe('return');
});

it('returns label for all pre-shipment statuses', function (): void {
    expect(TrackingStatus::AwaitingPickup->getLabel())->toBe('Awaiting Pickup');
    expect(TrackingStatus::PickedUp->getLabel())->toBe('Picked Up');
});

it('returns label for all in-transit statuses', function (): void {
    expect(TrackingStatus::ArrivedAtFacility->getLabel())->toBe('Arrived at Facility');
    expect(TrackingStatus::DepartedFacility->getLabel())->toBe('Departed Facility');
    expect(TrackingStatus::InCustoms->getLabel())->toBe('In Customs');
    expect(TrackingStatus::CustomsCleared->getLabel())->toBe('Customs Cleared');
});

it('returns label for all delivered statuses', function (): void {
    expect(TrackingStatus::DeliveredToNeighbor->getLabel())->toBe('Delivered to Neighbor');
    expect(TrackingStatus::DeliveredToLocker->getLabel())->toBe('Delivered to Locker');
    expect(TrackingStatus::SignedFor->getLabel())->toBe('Signed For');
});

it('returns label for all exception statuses', function (): void {
    expect(TrackingStatus::AddressIssue->getLabel())->toBe('Address Issue');
    expect(TrackingStatus::CustomerRefused->getLabel())->toBe('Customer Refused');
    expect(TrackingStatus::Damaged->getLabel())->toBe('Damaged');
    expect(TrackingStatus::Delayed->getLabel())->toBe('Delayed');
    expect(TrackingStatus::OnHold->getLabel())->toBe('On Hold');
});

it('returns label for all return statuses', function (): void {
    expect(TrackingStatus::ReturnToSender->getLabel())->toBe('Return to Sender');
    expect(TrackingStatus::ReturnInTransit->getLabel())->toBe('Return In Transit');
    expect(TrackingStatus::ReturnDelivered->getLabel())->toBe('Return Delivered');
});

it('returns correct category for all in-transit statuses', function (): void {
    expect(TrackingStatus::ArrivedAtFacility->getCategory())->toBe('in_transit');
    expect(TrackingStatus::DepartedFacility->getCategory())->toBe('in_transit');
    expect(TrackingStatus::InCustoms->getCategory())->toBe('in_transit');
    expect(TrackingStatus::CustomsCleared->getCategory())->toBe('in_transit');
});

it('returns correct category for all delivered statuses', function (): void {
    expect(TrackingStatus::DeliveredToNeighbor->getCategory())->toBe('delivered');
    expect(TrackingStatus::DeliveredToLocker->getCategory())->toBe('delivered');
    expect(TrackingStatus::SignedFor->getCategory())->toBe('delivered');
});

it('returns correct category for all exception statuses', function (): void {
    expect(TrackingStatus::DeliveryAttemptFailed->getCategory())->toBe('exception');
    expect(TrackingStatus::AddressIssue->getCategory())->toBe('exception');
    expect(TrackingStatus::CustomerRefused->getCategory())->toBe('exception');
    expect(TrackingStatus::Damaged->getCategory())->toBe('exception');
    expect(TrackingStatus::Delayed->getCategory())->toBe('exception');
    expect(TrackingStatus::OnHold->getCategory())->toBe('exception');
});

it('returns correct category for all return statuses', function (): void {
    expect(TrackingStatus::ReturnInTransit->getCategory())->toBe('return');
    expect(TrackingStatus::ReturnDelivered->getCategory())->toBe('return');
});

it('returns correct icon for all categories', function (): void {
    expect(TrackingStatus::AwaitingPickup->getIcon())->toBe('heroicon-o-clock');
    expect(TrackingStatus::InTransit->getIcon())->toBe('heroicon-o-truck');
    expect(TrackingStatus::OutForDelivery->getIcon())->toBe('heroicon-o-map-pin');
    expect(TrackingStatus::ReturnToSender->getIcon())->toBe('heroicon-o-arrow-uturn-left');
});

it('returns correct color for all categories', function (): void {
    expect(TrackingStatus::AwaitingPickup->getColor())->toBe('gray');
    expect(TrackingStatus::InTransit->getColor())->toBe('blue');
    expect(TrackingStatus::OutForDelivery->getColor())->toBe('indigo');
    expect(TrackingStatus::ReturnToSender->getColor())->toBe('orange');
});

it('identifies all terminal delivered statuses', function (): void {
    expect(TrackingStatus::DeliveredToNeighbor->isTerminal())->toBeTrue();
    expect(TrackingStatus::DeliveredToLocker->isTerminal())->toBeTrue();
    expect(TrackingStatus::SignedFor->isTerminal())->toBeTrue();
});
