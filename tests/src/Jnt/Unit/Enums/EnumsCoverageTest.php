<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\ExpressType;
use AIArmada\Jnt\Enums\GoodsType;
use AIArmada\Jnt\Enums\PaymentType;
use AIArmada\Jnt\Enums\ScanTypeCode;
use AIArmada\Jnt\Enums\ServiceType;
use AIArmada\Jnt\Enums\TrackingStatus;

describe('ExpressType enum', function (): void {
    it('has expected cases', function (): void {
        expect(ExpressType::cases())->toHaveCount(5);
        expect(ExpressType::DOMESTIC->value)->toBe('EZ');
        expect(ExpressType::NEXT_DAY->value)->toBe('EX');
        expect(ExpressType::FRESH->value)->toBe('FD');
        expect(ExpressType::DOOR_TO_DOOR->value)->toBe('DO');
        expect(ExpressType::SAME_DAY->value)->toBe('JS');
    });

    it('returns labels correctly', function (): void {
        expect(ExpressType::DOMESTIC->label())->toBe('Domestic Standard');
        expect(ExpressType::NEXT_DAY->label())->toBe('Express Next Day');
        expect(ExpressType::FRESH->label())->toBe('Fresh Delivery');
        expect(ExpressType::DOOR_TO_DOOR->label())->toBe('Door to Door');
        expect(ExpressType::SAME_DAY->label())->toBe('Same Day');
    });

    it('can be created from string value', function (): void {
        expect(ExpressType::from('EZ'))->toBe(ExpressType::DOMESTIC);
        expect(ExpressType::tryFrom('INVALID'))->toBeNull();
    });
});

describe('GoodsType enum', function (): void {
    it('has expected cases', function (): void {
        expect(GoodsType::cases())->toHaveCount(2);
        expect(GoodsType::DOCUMENT->value)->toBe('ITN2');
        expect(GoodsType::PACKAGE->value)->toBe('ITN8');
    });

    it('returns labels correctly', function (): void {
        expect(GoodsType::DOCUMENT->label())->toBe('Document');
        expect(GoodsType::PACKAGE->label())->toBe('Package');
    });

    it('can be created from string value', function (): void {
        expect(GoodsType::from('ITN2'))->toBe(GoodsType::DOCUMENT);
        expect(GoodsType::tryFrom('INVALID'))->toBeNull();
    });
});

describe('PaymentType enum', function (): void {
    it('has expected cases', function (): void {
        expect(PaymentType::cases())->toHaveCount(3);
        expect(PaymentType::PREPAID_POSTPAID->value)->toBe('PP_PM');
        expect(PaymentType::PREPAID_CASH->value)->toBe('PP_CASH');
        expect(PaymentType::COLLECT_CASH->value)->toBe('CC_CASH');
    });

    it('returns labels correctly', function (): void {
        expect(PaymentType::PREPAID_POSTPAID->label())->toBe('Prepaid - Postpaid by Merchant');
        expect(PaymentType::PREPAID_CASH->label())->toBe('Prepaid - Cash');
        expect(PaymentType::COLLECT_CASH->label())->toBe('Cash on Delivery');
    });

    it('can be created from string value', function (): void {
        expect(PaymentType::from('PP_PM'))->toBe(PaymentType::PREPAID_POSTPAID);
        expect(PaymentType::tryFrom('INVALID'))->toBeNull();
    });
});

describe('ServiceType enum', function (): void {
    it('has expected cases', function (): void {
        expect(ServiceType::cases())->toHaveCount(2);
        expect(ServiceType::DOOR_TO_DOOR->value)->toBe('1');
        expect(ServiceType::WALK_IN->value)->toBe('6');
    });

    it('returns labels correctly', function (): void {
        expect(ServiceType::DOOR_TO_DOOR->label())->toBe('Door to Door');
        expect(ServiceType::WALK_IN->label())->toBe('Walk-In');
    });

    it('can be created from string value', function (): void {
        expect(ServiceType::from('1'))->toBe(ServiceType::DOOR_TO_DOOR);
        expect(ServiceType::tryFrom('INVALID'))->toBeNull();
    });
});

describe('ScanTypeCode enum', function (): void {
    it('has expected cargo/customs cases', function (): void {
        expect(ScanTypeCode::PICKED_UP_FROM_CARGO->value)->toBe('400');
        expect(ScanTypeCode::CUSTOMS_CLEARANCE_IN_PROCESS->value)->toBe('401');
        expect(ScanTypeCode::CUSTOMS_CLEARANCE->value)->toBe('402');
        expect(ScanTypeCode::DELIVERED_TO_HUB->value)->toBe('403');
        expect(ScanTypeCode::PACKAGE_INBOUND->value)->toBe('404');
        expect(ScanTypeCode::CENTER_INBOUND->value)->toBe('405');
    });

    it('has expected normal flow cases', function (): void {
        expect(ScanTypeCode::PARCEL_PICKUP->value)->toBe('10');
        expect(ScanTypeCode::OUTBOUND_SCAN->value)->toBe('20');
        expect(ScanTypeCode::ARRIVAL->value)->toBe('30');
        expect(ScanTypeCode::DELIVERY_SCAN->value)->toBe('94');
        expect(ScanTypeCode::PARCEL_SIGNED->value)->toBe('100');
    });

    it('has expected problem and return cases', function (): void {
        expect(ScanTypeCode::PROBLEMATIC_SCANNING->value)->toBe('110');
        expect(ScanTypeCode::RETURN_SCAN->value)->toBe('172');
        expect(ScanTypeCode::RETURN_SIGN->value)->toBe('173');
    });

    it('has expected terminal state cases', function (): void {
        expect(ScanTypeCode::COLLECTED->value)->toBe('200');
        expect(ScanTypeCode::DAMAGE_PARCEL->value)->toBe('201');
        expect(ScanTypeCode::LOST_PARCEL->value)->toBe('300');
        expect(ScanTypeCode::DISPOSE_PARCEL->value)->toBe('301');
        expect(ScanTypeCode::REJECT_PARCEL->value)->toBe('302');
        expect(ScanTypeCode::CUSTOMS_CONFISCATED->value)->toBe('303');
        expect(ScanTypeCode::EXCEED_LIFE_CYCLE->value)->toBe('304');
        expect(ScanTypeCode::CROSSBORDER_DISPOSE->value)->toBe('305');
        expect(ScanTypeCode::COLLECTED_ALT->value)->toBe('306');
    });

    it('creates from value correctly', function (): void {
        expect(ScanTypeCode::fromValue('100'))->toBe(ScanTypeCode::PARCEL_SIGNED);
        expect(ScanTypeCode::fromValue('INVALID'))->toBeNull();
    });

    it('gets descriptions for all cases', function (): void {
        expect(ScanTypeCode::PICKED_UP_FROM_CARGO->getDescription())->toBe('Picked Up from Cargo Station');
        expect(ScanTypeCode::CUSTOMS_CLEARANCE_IN_PROCESS->getDescription())->toBe('Customs Clearance in Process');
        expect(ScanTypeCode::CUSTOMS_CLEARANCE->getDescription())->toBe('Customs Clearance');
        expect(ScanTypeCode::DELIVERED_TO_HUB->getDescription())->toBe('Delivered to Hub');
        expect(ScanTypeCode::PACKAGE_INBOUND->getDescription())->toBe('Package Inbound');
        expect(ScanTypeCode::CENTER_INBOUND->getDescription())->toBe('Center Inbound');
        expect(ScanTypeCode::PARCEL_PICKUP->getDescription())->toBe('Parcel Pickup');
        expect(ScanTypeCode::OUTBOUND_SCAN->getDescription())->toBe('Outbound Scan');
        expect(ScanTypeCode::ARRIVAL->getDescription())->toBe('Arrival');
        expect(ScanTypeCode::DELIVERY_SCAN->getDescription())->toBe('Delivery Scan');
        expect(ScanTypeCode::PARCEL_SIGNED->getDescription())->toBe('Parcel Signed');
        expect(ScanTypeCode::PROBLEMATIC_SCANNING->getDescription())->toBe('Problematic Scanning');
        expect(ScanTypeCode::RETURN_SCAN->getDescription())->toBe('Return Scan');
        expect(ScanTypeCode::RETURN_SIGN->getDescription())->toBe('Return Sign');
        expect(ScanTypeCode::COLLECTED->getDescription())->toBe('Damage Parcel');
        expect(ScanTypeCode::DAMAGE_PARCEL->getDescription())->toBe('Damage Parcel');
        expect(ScanTypeCode::LOST_PARCEL->getDescription())->toBe('Lost Parcel');
        expect(ScanTypeCode::DISPOSE_PARCEL->getDescription())->toBe('Dispose Parcel');
        expect(ScanTypeCode::REJECT_PARCEL->getDescription())->toBe('Reject Parcel');
        expect(ScanTypeCode::CUSTOMS_CONFISCATED->getDescription())->toBe('Customs Confiscated Parcel');
        expect(ScanTypeCode::EXCEED_LIFE_CYCLE->getDescription())->toBe('Exceed Life Cycle Parcel');
        expect(ScanTypeCode::CROSSBORDER_DISPOSE->getDescription())->toBe('Crossborder Dispose Parcel');
        expect(ScanTypeCode::COLLECTED_ALT->getDescription())->toBe('Collected');
    });

    it('identifies terminal states correctly', function (): void {
        expect(ScanTypeCode::PARCEL_SIGNED->isTerminalState())->toBeFalse();
        expect(ScanTypeCode::PARCEL_PICKUP->isTerminalState())->toBeFalse();
        expect(ScanTypeCode::COLLECTED->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::DAMAGE_PARCEL->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::LOST_PARCEL->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::DISPOSE_PARCEL->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::REJECT_PARCEL->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::CUSTOMS_CONFISCATED->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::EXCEED_LIFE_CYCLE->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::CROSSBORDER_DISPOSE->isTerminalState())->toBeTrue();
        expect(ScanTypeCode::COLLECTED_ALT->isTerminalState())->toBeTrue();
    });

    it('identifies successful delivery correctly', function (): void {
        expect(ScanTypeCode::PARCEL_SIGNED->isSuccessfulDelivery())->toBeTrue();
        expect(ScanTypeCode::PARCEL_PICKUP->isSuccessfulDelivery())->toBeFalse();
        expect(ScanTypeCode::COLLECTED->isSuccessfulDelivery())->toBeFalse();
    });

    it('identifies problems correctly', function (): void {
        expect(ScanTypeCode::PROBLEMATIC_SCANNING->isProblem())->toBeTrue();
        expect(ScanTypeCode::COLLECTED->isProblem())->toBeTrue();
        expect(ScanTypeCode::DAMAGE_PARCEL->isProblem())->toBeTrue();
        expect(ScanTypeCode::LOST_PARCEL->isProblem())->toBeTrue();
        expect(ScanTypeCode::PARCEL_PICKUP->isProblem())->toBeFalse();
        expect(ScanTypeCode::PARCEL_SIGNED->isProblem())->toBeFalse();
    });

    it('identifies returns correctly', function (): void {
        expect(ScanTypeCode::RETURN_SCAN->isReturn())->toBeTrue();
        expect(ScanTypeCode::RETURN_SIGN->isReturn())->toBeTrue();
        expect(ScanTypeCode::PARCEL_PICKUP->isReturn())->toBeFalse();
        expect(ScanTypeCode::PARCEL_SIGNED->isReturn())->toBeFalse();
    });

    it('identifies customs correctly', function (): void {
        expect(ScanTypeCode::CUSTOMS_CLEARANCE_IN_PROCESS->isCustoms())->toBeTrue();
        expect(ScanTypeCode::CUSTOMS_CLEARANCE->isCustoms())->toBeTrue();
        expect(ScanTypeCode::CUSTOMS_CONFISCATED->isCustoms())->toBeTrue();
        expect(ScanTypeCode::PARCEL_PICKUP->isCustoms())->toBeFalse();
        expect(ScanTypeCode::PICKED_UP_FROM_CARGO->isCustoms())->toBeFalse();
    });

    it('gets category correctly', function (): void {
        expect(ScanTypeCode::PICKED_UP_FROM_CARGO->getCategory())->toBe('Cargo/Customs');
        expect(ScanTypeCode::CENTER_INBOUND->getCategory())->toBe('Cargo/Customs');
        expect(ScanTypeCode::PARCEL_PICKUP->getCategory())->toBe('Normal Flow');
        expect(ScanTypeCode::PARCEL_SIGNED->getCategory())->toBe('Normal Flow');
        expect(ScanTypeCode::PROBLEMATIC_SCANNING->getCategory())->toBe('Problems/Returns');
        expect(ScanTypeCode::RETURN_SIGN->getCategory())->toBe('Problems/Returns');
        expect(ScanTypeCode::COLLECTED->getCategory())->toBe('Terminal States');
        expect(ScanTypeCode::LOST_PARCEL->getCategory())->toBe('Terminal States');
    });
});

describe('TrackingStatus enum', function (): void {
    it('has expected cases', function (): void {
        expect(TrackingStatus::cases())->toHaveCount(10);
        expect(TrackingStatus::Pending->value)->toBe('pending');
        expect(TrackingStatus::PickedUp->value)->toBe('picked_up');
        expect(TrackingStatus::InTransit->value)->toBe('in_transit');
        expect(TrackingStatus::AtHub->value)->toBe('at_hub');
        expect(TrackingStatus::OutForDelivery->value)->toBe('out_for_delivery');
        expect(TrackingStatus::DeliveryAttempted->value)->toBe('delivery_attempted');
        expect(TrackingStatus::Delivered->value)->toBe('delivered');
        expect(TrackingStatus::ReturnInitiated->value)->toBe('return_initiated');
        expect(TrackingStatus::Returned->value)->toBe('returned');
        expect(TrackingStatus::Exception->value)->toBe('exception');
    });

    it('returns labels correctly', function (): void {
        expect(TrackingStatus::Pending->label())->toBe('Pending');
        expect(TrackingStatus::PickedUp->label())->toBe('Picked Up');
        expect(TrackingStatus::InTransit->label())->toBe('In Transit');
        expect(TrackingStatus::AtHub->label())->toBe('At Hub');
        expect(TrackingStatus::OutForDelivery->label())->toBe('Out for Delivery');
        expect(TrackingStatus::DeliveryAttempted->label())->toBe('Delivery Attempted');
        expect(TrackingStatus::Delivered->label())->toBe('Delivered');
        expect(TrackingStatus::ReturnInitiated->label())->toBe('Return Initiated');
        expect(TrackingStatus::Returned->label())->toBe('Returned');
        expect(TrackingStatus::Exception->label())->toBe('Exception');
    });

    it('returns icons correctly', function (): void {
        expect(TrackingStatus::Pending->icon())->toBe('heroicon-o-clock');
        expect(TrackingStatus::PickedUp->icon())->toBe('heroicon-o-truck');
        expect(TrackingStatus::InTransit->icon())->toBe('heroicon-o-arrow-path');
        expect(TrackingStatus::AtHub->icon())->toBe('heroicon-o-building-office');
        expect(TrackingStatus::OutForDelivery->icon())->toBe('heroicon-o-map-pin');
        expect(TrackingStatus::DeliveryAttempted->icon())->toBe('heroicon-o-exclamation-circle');
        expect(TrackingStatus::Delivered->icon())->toBe('heroicon-o-check-circle');
        expect(TrackingStatus::ReturnInitiated->icon())->toBe('heroicon-o-arrow-uturn-left');
        expect(TrackingStatus::Returned->icon())->toBe('heroicon-o-arrow-uturn-left');
        expect(TrackingStatus::Exception->icon())->toBe('heroicon-o-x-circle');
    });

    it('returns colors correctly', function (): void {
        expect(TrackingStatus::Pending->color())->toBe('gray');
        expect(TrackingStatus::PickedUp->color())->toBe('blue');
        expect(TrackingStatus::InTransit->color())->toBe('blue');
        expect(TrackingStatus::AtHub->color())->toBe('blue');
        expect(TrackingStatus::OutForDelivery->color())->toBe('yellow');
        expect(TrackingStatus::DeliveryAttempted->color())->toBe('orange');
        expect(TrackingStatus::Delivered->color())->toBe('green');
        expect(TrackingStatus::ReturnInitiated->color())->toBe('purple');
        expect(TrackingStatus::Returned->color())->toBe('purple');
        expect(TrackingStatus::Exception->color())->toBe('red');
    });

    it('identifies terminal states correctly', function (): void {
        expect(TrackingStatus::Pending->isTerminal())->toBeFalse();
        expect(TrackingStatus::PickedUp->isTerminal())->toBeFalse();
        expect(TrackingStatus::InTransit->isTerminal())->toBeFalse();
        expect(TrackingStatus::AtHub->isTerminal())->toBeFalse();
        expect(TrackingStatus::OutForDelivery->isTerminal())->toBeFalse();
        expect(TrackingStatus::DeliveryAttempted->isTerminal())->toBeFalse();
        expect(TrackingStatus::Delivered->isTerminal())->toBeTrue();
        expect(TrackingStatus::ReturnInitiated->isTerminal())->toBeFalse();
        expect(TrackingStatus::Returned->isTerminal())->toBeTrue();
        expect(TrackingStatus::Exception->isTerminal())->toBeTrue();
    });

    it('identifies successful states correctly', function (): void {
        expect(TrackingStatus::Delivered->isSuccessful())->toBeTrue();
        expect(TrackingStatus::PickedUp->isSuccessful())->toBeFalse();
        expect(TrackingStatus::Returned->isSuccessful())->toBeFalse();
    });

    it('identifies in-progress states correctly', function (): void {
        expect(TrackingStatus::Pending->isInProgress())->toBeFalse();
        expect(TrackingStatus::PickedUp->isInProgress())->toBeTrue();
        expect(TrackingStatus::InTransit->isInProgress())->toBeTrue();
        expect(TrackingStatus::AtHub->isInProgress())->toBeTrue();
        expect(TrackingStatus::OutForDelivery->isInProgress())->toBeTrue();
        expect(TrackingStatus::DeliveryAttempted->isInProgress())->toBeTrue();
        expect(TrackingStatus::Delivered->isInProgress())->toBeFalse();
        expect(TrackingStatus::ReturnInitiated->isInProgress())->toBeFalse();
        expect(TrackingStatus::Returned->isInProgress())->toBeFalse();
        expect(TrackingStatus::Exception->isInProgress())->toBeFalse();
    });

    it('identifies return states correctly', function (): void {
        expect(TrackingStatus::Pending->isReturn())->toBeFalse();
        expect(TrackingStatus::Delivered->isReturn())->toBeFalse();
        expect(TrackingStatus::ReturnInitiated->isReturn())->toBeTrue();
        expect(TrackingStatus::Returned->isReturn())->toBeTrue();
    });

    it('identifies states requiring attention correctly', function (): void {
        expect(TrackingStatus::Pending->requiresAttention())->toBeFalse();
        expect(TrackingStatus::PickedUp->requiresAttention())->toBeFalse();
        expect(TrackingStatus::InTransit->requiresAttention())->toBeFalse();
        expect(TrackingStatus::DeliveryAttempted->requiresAttention())->toBeTrue();
        expect(TrackingStatus::ReturnInitiated->requiresAttention())->toBeTrue();
        expect(TrackingStatus::Exception->requiresAttention())->toBeTrue();
        expect(TrackingStatus::Delivered->requiresAttention())->toBeFalse();
    });
});
