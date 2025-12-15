<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\ShipmentStatus;

// ============================================
// ShipmentStatus Enum Tests
// ============================================

it('has all expected shipment statuses', function (): void {
    $statuses = ShipmentStatus::cases();

    expect($statuses)->toHaveCount(12);
    expect(ShipmentStatus::Draft)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::Pending)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::AwaitingPickup)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::Shipped)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::InTransit)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::OutForDelivery)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::Delivered)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::DeliveryFailed)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::OnHold)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::Exception)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::Cancelled)->toBeInstanceOf(ShipmentStatus::class);
    expect(ShipmentStatus::ReturnToSender)->toBeInstanceOf(ShipmentStatus::class);
});

it('correctly identifies pending statuses', function (): void {
    expect(ShipmentStatus::Draft->isPending())->toBeTrue();
    expect(ShipmentStatus::Pending->isPending())->toBeTrue();
    expect(ShipmentStatus::InTransit->isPending())->toBeFalse();
    expect(ShipmentStatus::Delivered->isPending())->toBeFalse();
});

it('correctly identifies in-transit statuses', function (): void {
    expect(ShipmentStatus::Shipped->isInTransit())->toBeTrue();
    expect(ShipmentStatus::InTransit->isInTransit())->toBeTrue();
    expect(ShipmentStatus::OutForDelivery->isInTransit())->toBeTrue();
    expect(ShipmentStatus::Draft->isInTransit())->toBeFalse();
    expect(ShipmentStatus::Delivered->isInTransit())->toBeFalse();
});

it('correctly identifies delivered status', function (): void {
    expect(ShipmentStatus::Delivered->isDelivered())->toBeTrue();
    expect(ShipmentStatus::InTransit->isDelivered())->toBeFalse();
    expect(ShipmentStatus::Cancelled->isDelivered())->toBeFalse();
});

it('correctly identifies terminal statuses', function (): void {
    expect(ShipmentStatus::Delivered->isTerminal())->toBeTrue();
    expect(ShipmentStatus::Cancelled->isTerminal())->toBeTrue();
    expect(ShipmentStatus::ReturnToSender->isTerminal())->toBeTrue();
    expect(ShipmentStatus::InTransit->isTerminal())->toBeFalse();
    expect(ShipmentStatus::Pending->isTerminal())->toBeFalse();
});

it('correctly identifies cancellable statuses', function (): void {
    expect(ShipmentStatus::Draft->isCancellable())->toBeTrue();
    expect(ShipmentStatus::Pending->isCancellable())->toBeTrue();
    expect(ShipmentStatus::Delivered->isCancellable())->toBeFalse();
    expect(ShipmentStatus::Cancelled->isCancellable())->toBeFalse();
});

it('returns correct labels for statuses', function (): void {
    expect(ShipmentStatus::Draft->getLabel())->toBe('Draft');
    expect(ShipmentStatus::InTransit->getLabel())->toBe('In Transit');
    expect(ShipmentStatus::OutForDelivery->getLabel())->toBe('Out for Delivery');
    expect(ShipmentStatus::ReturnToSender->getLabel())->toBe('Return to Sender');
});

it('returns appropriate colors for statuses', function (): void {
    expect(ShipmentStatus::Draft->getColor())->toBe('gray');
    expect(ShipmentStatus::Pending->getColor())->toBe('yellow');
    expect(ShipmentStatus::InTransit->getColor())->toBe('blue');
    expect(ShipmentStatus::Delivered->getColor())->toBe('green');
    expect(ShipmentStatus::Cancelled->getColor())->toBe('gray');
    expect(ShipmentStatus::Exception->getColor())->toBe('red');
});

it('validates status transitions correctly', function (): void {
    // Draft can transition to Pending
    expect(ShipmentStatus::Draft->canTransitionTo(ShipmentStatus::Pending))->toBeTrue();

    // Pending can transition to Shipped
    expect(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Shipped))->toBeTrue();

    // InTransit can transition to Delivered
    expect(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Delivered))->toBeTrue();

    // Delivered cannot transition to InTransit (terminal)
    expect(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::InTransit))->toBeFalse();

    // Cancelled cannot transition to anything (terminal)
    expect(ShipmentStatus::Cancelled->canTransitionTo(ShipmentStatus::Pending))->toBeFalse();
});

it('returns allowed transitions for each status', function (): void {
    $draftTransitions = ShipmentStatus::Draft->getAllowedTransitions();

    expect($draftTransitions)->toContain(ShipmentStatus::Pending);
    expect($draftTransitions)->toContain(ShipmentStatus::Cancelled);
    expect($draftTransitions)->not->toContain(ShipmentStatus::Delivered);

    $deliveredTransitions = ShipmentStatus::Delivered->getAllowedTransitions();
    expect($deliveredTransitions)->toBeEmpty();
});

it('returns correct icons for statuses', function (): void {
    expect(ShipmentStatus::Draft->getIcon())->toBe('heroicon-o-document');
    expect(ShipmentStatus::Pending->getIcon())->toBe('heroicon-o-clock');
    expect(ShipmentStatus::Delivered->getIcon())->toBe('heroicon-o-check-circle');
    expect(ShipmentStatus::Exception->getIcon())->toBe('heroicon-o-exclamation-triangle');
});

it('returns labels for all statuses', function (): void {
    expect(ShipmentStatus::Pending->getLabel())->toBe('Pending');
    expect(ShipmentStatus::AwaitingPickup->getLabel())->toBe('Awaiting Pickup');
    expect(ShipmentStatus::Shipped->getLabel())->toBe('Shipped');
    expect(ShipmentStatus::DeliveryFailed->getLabel())->toBe('Delivery Failed');
    expect(ShipmentStatus::Cancelled->getLabel())->toBe('Cancelled');
    expect(ShipmentStatus::OnHold->getLabel())->toBe('On Hold');
    expect(ShipmentStatus::Exception->getLabel())->toBe('Exception');
    expect(ShipmentStatus::Delivered->getLabel())->toBe('Delivered');
});

it('returns icons for all statuses', function (): void {
    expect(ShipmentStatus::AwaitingPickup->getIcon())->toBe('heroicon-o-truck');
    expect(ShipmentStatus::Shipped->getIcon())->toBe('heroicon-o-paper-airplane');
    expect(ShipmentStatus::InTransit->getIcon())->toBe('heroicon-o-truck');
    expect(ShipmentStatus::OutForDelivery->getIcon())->toBe('heroicon-o-map-pin');
    expect(ShipmentStatus::DeliveryFailed->getIcon())->toBe('heroicon-o-x-circle');
    expect(ShipmentStatus::ReturnToSender->getIcon())->toBe('heroicon-o-arrow-uturn-left');
    expect(ShipmentStatus::Cancelled->getIcon())->toBe('heroicon-o-x-mark');
    expect(ShipmentStatus::OnHold->getIcon())->toBe('heroicon-o-pause-circle');
});

it('returns colors for all statuses', function (): void {
    expect(ShipmentStatus::AwaitingPickup->getColor())->toBe('blue');
    expect(ShipmentStatus::Shipped->getColor())->toBe('indigo');
    expect(ShipmentStatus::OutForDelivery->getColor())->toBe('cyan');
    expect(ShipmentStatus::DeliveryFailed->getColor())->toBe('red');
    expect(ShipmentStatus::ReturnToSender->getColor())->toBe('orange');
    expect(ShipmentStatus::OnHold->getColor())->toBe('yellow');
});

it('converts to tracking status for all statuses', function (): void {
    expect(ShipmentStatus::Draft->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::LabelCreated);
    expect(ShipmentStatus::Pending->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::AwaitingPickup);
    expect(ShipmentStatus::AwaitingPickup->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::AwaitingPickup);
    expect(ShipmentStatus::Shipped->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::PickedUp);
    expect(ShipmentStatus::InTransit->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::InTransit);
    expect(ShipmentStatus::OutForDelivery->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::OutForDelivery);
    expect(ShipmentStatus::Delivered->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::Delivered);
    expect(ShipmentStatus::DeliveryFailed->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::DeliveryAttemptFailed);
    expect(ShipmentStatus::ReturnToSender->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::ReturnToSender);
    expect(ShipmentStatus::Cancelled->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::OnHold);
    expect(ShipmentStatus::OnHold->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::OnHold);
    expect(ShipmentStatus::Exception->toTrackingStatus())->toBe(\AIArmada\Shipping\Enums\TrackingStatus::Delayed);
});

it('returns allowed transitions for all statuses', function (): void {
    // Test specific transitions for various statuses
    expect(ShipmentStatus::Pending->getAllowedTransitions())
        ->toContain(ShipmentStatus::AwaitingPickup)
        ->toContain(ShipmentStatus::Shipped)
        ->toContain(ShipmentStatus::Cancelled);

    expect(ShipmentStatus::AwaitingPickup->getAllowedTransitions())
        ->toContain(ShipmentStatus::Shipped)
        ->toContain(ShipmentStatus::Cancelled);

    expect(ShipmentStatus::Shipped->getAllowedTransitions())
        ->toContain(ShipmentStatus::InTransit)
        ->toContain(ShipmentStatus::Exception);

    expect(ShipmentStatus::InTransit->getAllowedTransitions())
        ->toContain(ShipmentStatus::OutForDelivery)
        ->toContain(ShipmentStatus::Delivered)
        ->toContain(ShipmentStatus::DeliveryFailed)
        ->toContain(ShipmentStatus::Exception)
        ->toContain(ShipmentStatus::OnHold);

    expect(ShipmentStatus::OutForDelivery->getAllowedTransitions())
        ->toContain(ShipmentStatus::Delivered)
        ->toContain(ShipmentStatus::DeliveryFailed)
        ->toContain(ShipmentStatus::Exception);

    expect(ShipmentStatus::DeliveryFailed->getAllowedTransitions())
        ->toContain(ShipmentStatus::InTransit)
        ->toContain(ShipmentStatus::OutForDelivery)
        ->toContain(ShipmentStatus::ReturnToSender);

    expect(ShipmentStatus::Exception->getAllowedTransitions())
        ->toContain(ShipmentStatus::InTransit)
        ->toContain(ShipmentStatus::OnHold)
        ->toContain(ShipmentStatus::ReturnToSender);

    expect(ShipmentStatus::OnHold->getAllowedTransitions())
        ->toContain(ShipmentStatus::InTransit)
        ->toContain(ShipmentStatus::Cancelled);

    // Terminal statuses have no transitions
    expect(ShipmentStatus::Delivered->getAllowedTransitions())->toBeEmpty();
    expect(ShipmentStatus::Cancelled->getAllowedTransitions())->toBeEmpty();
    expect(ShipmentStatus::ReturnToSender->getAllowedTransitions())->toBeEmpty();
});
