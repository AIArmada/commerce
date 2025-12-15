<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\ShipmentResultData;

describe('ShipmentResultData', function (): void {
    it('can create successful result with tracking', function (): void {
        $result = new ShipmentResultData(
            success: true,
            trackingNumber: 'TRACK123',
            carrierReference: 'CARRIER123',
            labelUrl: 'https://example.com/label.pdf',
            rawResponse: ['success' => true]
        );

        expect($result->success)->toBeTrue();
        expect($result->trackingNumber)->toBe('TRACK123');
        expect($result->carrierReference)->toBe('CARRIER123');
        expect($result->labelUrl)->toBe('https://example.com/label.pdf');
        expect($result->error)->toBeNull();
        expect($result->errors)->toBe([]);
        expect($result->requiresManualFulfillment)->toBeFalse();
        expect($result->rawResponse)->toBe(['success' => true]);
    });

    it('can create failed result with errors', function (): void {
        $result = new ShipmentResultData(
            success: false,
            error: 'Invalid address',
            errors: ['Address line 1 is required', 'City is required'],
            requiresManualFulfillment: true
        );

        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Invalid address');
        expect($result->errors)->toBe(['Address line 1 is required', 'City is required']);
        expect($result->requiresManualFulfillment)->toBeTrue();
        expect($result->trackingNumber)->toBeNull();
    });

    it('checks if result is successful', function (): void {
        $success = new ShipmentResultData(success: true);
        $failure = new ShipmentResultData(success: false);

        expect($success->isSuccessful())->toBeTrue();
        expect($failure->isSuccessful())->toBeFalse();
    });

    it('returns tracking number', function (): void {
        $withTracking = new ShipmentResultData(success: true, trackingNumber: 'TRACK456');
        $withoutTracking = new ShipmentResultData(success: true);

        expect($withTracking->getTrackingNumber())->toBe('TRACK456');
        expect($withoutTracking->getTrackingNumber())->toBeNull();
    });
});