<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\AddressType;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Enums\SegmentType;

describe('AddressType Enum', function (): void {
    it('has correct values', function (): void {
        expect(AddressType::Billing->value)->toBe('billing')
            ->and(AddressType::Shipping->value)->toBe('shipping')
            ->and(AddressType::Both->value)->toBe('both');
    });

    it('returns correct labels', function (): void {
        expect(AddressType::Billing->label())->toBeString()
            ->and(AddressType::Shipping->label())->toBeString()
            ->and(AddressType::Both->label())->toBeString();
    });

    it('checks if billing correctly', function (): void {
        expect(AddressType::Billing->isBilling())->toBeTrue()
            ->and(AddressType::Both->isBilling())->toBeTrue()
            ->and(AddressType::Shipping->isBilling())->toBeFalse();
    });

    it('checks if shipping correctly', function (): void {
        expect(AddressType::Shipping->isShipping())->toBeTrue()
            ->and(AddressType::Both->isShipping())->toBeTrue()
            ->and(AddressType::Billing->isShipping())->toBeFalse();
    });
});

describe('CustomerStatus Enum', function (): void {
    it('has correct values', function (): void {
        expect(CustomerStatus::Active->value)->toBe('active')
            ->and(CustomerStatus::Inactive->value)->toBe('inactive')
            ->and(CustomerStatus::Suspended->value)->toBe('suspended')
            ->and(CustomerStatus::PendingVerification->value)->toBe('pending_verification');
    });

    it('returns correct labels', function (): void {
        expect(CustomerStatus::Active->label())->toBeString()
            ->and(CustomerStatus::Inactive->label())->toBeString()
            ->and(CustomerStatus::Suspended->label())->toBeString()
            ->and(CustomerStatus::PendingVerification->label())->toBeString();
    });

    it('returns correct colors', function (): void {
        expect(CustomerStatus::Active->color())->toBe('success')
            ->and(CustomerStatus::Inactive->color())->toBe('gray')
            ->and(CustomerStatus::Suspended->color())->toBe('danger')
            ->and(CustomerStatus::PendingVerification->color())->toBe('warning');
    });

    it('returns correct icons', function (): void {
        expect(CustomerStatus::Active->icon())->toBe('heroicon-o-check-circle')
            ->and(CustomerStatus::Inactive->icon())->toBe('heroicon-o-minus-circle')
            ->and(CustomerStatus::Suspended->icon())->toBe('heroicon-o-no-symbol')
            ->and(CustomerStatus::PendingVerification->icon())->toBe('heroicon-o-clock');
    });

    it('determines if can place orders', function (): void {
        expect(CustomerStatus::Active->canPlaceOrders())->toBeTrue()
            ->and(CustomerStatus::Inactive->canPlaceOrders())->toBeFalse()
            ->and(CustomerStatus::Suspended->canPlaceOrders())->toBeFalse()
            ->and(CustomerStatus::PendingVerification->canPlaceOrders())->toBeFalse();
    });
});

describe('SegmentType Enum', function (): void {
    it('has correct values', function (): void {
        expect(SegmentType::Loyalty->value)->toBe('loyalty')
            ->and(SegmentType::Behavior->value)->toBe('behavior')
            ->and(SegmentType::Demographic->value)->toBe('demographic')
            ->and(SegmentType::Custom->value)->toBe('custom');
    });

    it('returns correct labels', function (): void {
        expect(SegmentType::Loyalty->label())->toBeString()
            ->and(SegmentType::Behavior->label())->toBeString()
            ->and(SegmentType::Demographic->label())->toBeString()
            ->and(SegmentType::Custom->label())->toBeString();
    });

    it('returns correct colors', function (): void {
        expect(SegmentType::Loyalty->color())->toBe('warning')
            ->and(SegmentType::Behavior->color())->toBe('info')
            ->and(SegmentType::Demographic->color())->toBe('gray')
            ->and(SegmentType::Custom->color())->toBe('primary');
    });
});
