<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\GoodsType;

describe('PackageInfoData', function (): void {
    it('creates from API array', function (): void {
        $apiData = [
            'packageQuantity' => '5',
            'weight' => '2.5',
            'packageValue' => '100.00',
            'goodsType' => 'ITN8',
            'length' => '10.0',
            'width' => '10.0',
            'height' => '10.0',
        ];

        $packageInfo = PackageInfoData::fromApiArray($apiData);

        expect($packageInfo->quantity)->toBe(5);
        expect($packageInfo->weight)->toBe(2.5);
        expect($packageInfo->value)->toBe(100.0);
        expect($packageInfo->goodsType)->toBe(GoodsType::PACKAGE);
        expect($packageInfo->length)->toBe(10.0);
    });

    it('converts to API array', function (): void {
        $packageInfo = new PackageInfoData(
            quantity: 2,
            weight: 3.5,
            value: 50.0,
            goodsType: GoodsType::DOCUMENT,
            length: 15.0,
            width: 20.0,
            height: 5.0,
        );

        $apiArray = $packageInfo->toApiArray();

        expect($apiArray)->toBeArray();
        expect($apiArray['packageQuantity'])->toBe('2');
        // TypeTransformer uses specific formatting, verify it matches expectation
        // Assuming TypeTransformer::forPackageWeight returns formatted string
        expect($apiArray['weight'])->toBe('3.50');
        expect($apiArray['goodsType'])->toBe('ITN2');
    });

    it('calculates volumetric weight', function (): void {
        $packageInfo = new PackageInfoData(
            quantity: 1,
            weight: 1.0,
            value: 10.0,
            goodsType: 'ITN8',
            length: 10.0,
            width: 10.0,
            height: 10.0, // 1000 cm3
        );

        // 1000 / 5000 = 0.2
        expect($packageInfo->getVolumetricWeight())->toBe(0.2);
    });

    it('returns null volumetric weight if dimensions missing', function (): void {
        $packageInfo = new PackageInfoData(
            quantity: 1,
            weight: 1.0,
            value: 10.0,
            goodsType: 'ITN8',
        );

        expect($packageInfo->getVolumetricWeight())->toBeNull();
    });

    it('calculates chargeable weight using actual weight', function (): void {
        $packageInfo = new PackageInfoData(
            quantity: 1,
            weight: 2.0, // Existing weight > Volumetric (0.2)
            value: 10.0,
            goodsType: 'ITN8',
            length: 10.0,
            width: 10.0,
            height: 10.0,
        );

        expect($packageInfo->getChargeableWeight())->toBe(2.0);
    });

    it('calculates chargeable weight using volumetric weight', function (): void {
        $packageInfo = new PackageInfoData(
            quantity: 1,
            weight: 0.1, // Existing weight < Volumetric (0.2)
            value: 10.0,
            goodsType: 'ITN8',
            length: 10.0,
            width: 10.0,
            height: 10.0,
        );

        expect($packageInfo->getChargeableWeight())->toBe(0.2);
    });

    it('calculates chargeable weight without dimensions returns actual weight', function (): void {
        $packageInfo = new PackageInfoData(
            quantity: 1,
            weight: 5.0,
            value: 10.0,
            goodsType: 'ITN8',
        );

        expect($packageInfo->getChargeableWeight())->toBe(5.0);
    });

    it('checks if document', function (): void {
        $doc = new PackageInfoData(
            quantity: 1,
            weight: 1.0,
            value: 1.0,
            goodsType: GoodsType::DOCUMENT,
        );
        expect($doc->isDocument())->toBeTrue();

        $parcel = new PackageInfoData(
            quantity: 1,
            weight: 1.0,
            value: 1.0,
            goodsType: GoodsType::PACKAGE,
        );
        expect($parcel->isDocument())->toBeFalse();
    });
});

describe('TrackingDetailData', function (): void {
    it('creates from API array', function (): void {
        $apiData = [
            'scanTime' => '2024-01-01 12:00:00',
            'desc' => 'Test',
            'scanTypeCode' => '1',
            'scanTypeName' => 'Pickup',
            'scanType' => 'PICKUP',
            'realWeight' => '1.5',
        ];

        $detail = TrackingDetailData::fromApiArray($apiData);

        expect($detail->scanTime)->toBe('2024-01-01 12:00:00');
        expect($detail->description)->toBe('Test');
        expect($detail->actualWeight)->toBe('1.5');
    });

    it('converts to API array', function (): void {
        $detail = new TrackingDetailData(
            scanTime: '2024-01-01 12:00:00',
            description: 'Test',
            scanTypeCode: '1',
            scanTypeName: 'Pickup',
            scanType: 'PICKUP',
            scanNetworkName: 'Test Hub',
        );

        $apiArray = $detail->toApiArray();

        expect($apiArray['scanTime'])->toBe('2024-01-01 12:00:00');
        expect($apiArray['desc'])->toBe('Test');
        expect($apiArray['scanNetworkName'])->toBe('Test Hub');
    });

    it('gets location string', function (): void {
        $detail = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: '...',
            scanNetworkProvince: 'Province',
            scanNetworkCity: 'City',
            scanNetworkArea: 'Area',
        );

        expect($detail->getLocation())->toBe('Area, City, Province');
    });

    it('gets location string from network name if parts missing', function (): void {
        $detail = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: '...',
            scanNetworkName: 'Hub A',
        );

        expect($detail->getLocation())->toBe('Hub A');
    });

    it('checks coordinates presence', function (): void {
        $withCoords = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: '...',
            longitude: '100',
            latitude: '3',
        );
        expect($withCoords->hasCoordinates())->toBeTrue();

        $withoutCoords = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: '...',
        );
        expect($withoutCoords->hasCoordinates())->toBeFalse();
    });

    it('checks delivered status', function (): void {
        $delivered = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: 'SIGN',
        );
        expect($delivered->isDelivered())->toBeTrue();

        $notDelivered = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: 'PICKUP',
        );
        expect($notDelivered->isDelivered())->toBeFalse();
    });

    it('checks in transit status', function (): void {
        $inTransit = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: 'ARRIVE',
        );
        expect($inTransit->isInTransit())->toBeTrue();

        $notInTransit = new TrackingDetailData(
            scanTime: '...',
            description: '...',
            scanTypeCode: '...',
            scanTypeName: '...',
            scanType: 'UNKNOWN',
        );
        expect($notInTransit->isInTransit())->toBeFalse();
    });
});
