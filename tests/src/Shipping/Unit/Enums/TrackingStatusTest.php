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
