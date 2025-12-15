<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\ShippingMethodData;

describe('ShippingMethodData', function (): void {
    it('can create shipping method data with required fields', function (): void {
        $method = new ShippingMethodData(
            code: 'standard',
            name: 'Standard Shipping'
        );

        expect($method->code)->toBe('standard');
        expect($method->name)->toBe('Standard Shipping');
        expect($method->description)->toBeNull();
        expect($method->minDays)->toBeNull();
        expect($method->maxDays)->toBeNull();
        expect($method->trackingAvailable)->toBeTrue();
        expect($method->signatureAvailable)->toBeFalse();
        expect($method->insuranceAvailable)->toBeFalse();
    });

    it('can create shipping method data with all fields', function (): void {
        $method = new ShippingMethodData(
            code: 'express',
            name: 'Express Delivery',
            description: 'Fast delivery within 24 hours',
            minDays: 1,
            maxDays: 2,
            trackingAvailable: true,
            signatureAvailable: true,
            insuranceAvailable: true
        );

        expect($method->code)->toBe('express');
        expect($method->name)->toBe('Express Delivery');
        expect($method->description)->toBe('Fast delivery within 24 hours');
        expect($method->minDays)->toBe(1);
        expect($method->maxDays)->toBe(2);
        expect($method->trackingAvailable)->toBeTrue();
        expect($method->signatureAvailable)->toBeTrue();
        expect($method->insuranceAvailable)->toBeTrue();
    });

    it('returns delivery estimate for single day', function (): void {
        $method = new ShippingMethodData(
            code: 'same_day',
            name: 'Same Day',
            minDays: 1,
            maxDays: 1
        );

        expect($method->getDeliveryEstimate())->toBe('1 day');
    });

    it('returns delivery estimate for multiple days', function (): void {
        $method = new ShippingMethodData(
            code: 'standard',
            name: 'Standard',
            minDays: 3,
            maxDays: 7
        );

        expect($method->getDeliveryEstimate())->toBe('3-7 days');
    });

    it('returns delivery estimate for same min and max days', function (): void {
        $method = new ShippingMethodData(
            code: 'two_day',
            name: 'Two Day',
            minDays: 2,
            maxDays: 2
        );

        expect($method->getDeliveryEstimate())->toBe('2 days');
    });

    it('returns null when no delivery estimate available', function (): void {
        $method = new ShippingMethodData(
            code: 'pickup',
            name: 'Store Pickup'
        );

        expect($method->getDeliveryEstimate())->toBeNull();
    });
});